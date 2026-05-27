
// Ultra-optimized cron job handler for process-queue function
import { serve } from "https://deno.land/std@0.168.0/http/server.ts";

// Supabase injects these secrets for deployed Edge Functions.
const SUPABASE_URL = Deno.env.get('SUPABASE_URL');
const ANON_KEY = Deno.env.get('SUPABASE_ANON_KEY');

// Handle the cron job with proper error handling
serve(async (_req) => {
  try {
    if (!SUPABASE_URL || !ANON_KEY) {
      throw new Error('Missing SUPABASE_URL or SUPABASE_ANON_KEY');
    }

    console.log('🕒 Starting scheduled ZIP queue processing job');
    
    // Add shorter timeout to avoid function hanging
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 12000); // 12 second timeout (reduced)
    
    try {
      // Call the process-queue function with minimal configuration
      // IMPORTANT: Do not send any body data for cron job
      const response = await fetch(`${SUPABASE_URL}/functions/v1/process-queue`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'Authorization': `Bearer ${ANON_KEY}`
        },
        // Explicitly sending empty body to avoid any parsing issues
        // body: undefined,
        signal: controller.signal
      });
      
      clearTimeout(timeoutId);
      
      // Special handling for resource limit errors (546)
      if (response.status === 546) {
        console.log('⚠️ Worker resource limit reached, function will run again on next cron cycle');
        return new Response(
          JSON.stringify({ 
            success: true, // Mark as success to prevent retries
            message: 'Function resource limit reached, scheduled to retry on next cycle',
            resourceLimited: true
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      
      // Handle other error status codes
      if (!response.ok) {
        let errorText = 'Unknown error';
        try {
          errorText = await response.text();
        } catch (err) {
          // Ignore text parsing errors
        }
        
        throw new Error(`Failed to trigger process-queue: ${response.status} - ${errorText}`);
      }
      
      // Parse the response with error handling
      let result = { message: 'OK' };
      try {
        result = await response.json();
      } catch (err) {
        // If response isn't valid JSON, create a generic success result
      }
      
      console.log('⚡ ZIP queue processing job completed successfully');
      
      return new Response(
        JSON.stringify({ 
          success: true, 
          message: 'ZIP queue processing job completed successfully',
          data: result 
        }),
        { headers: { 'Content-Type': 'application/json' } }
      );
    } catch (error) {
      clearTimeout(timeoutId);
      
      // Check if the error was due to the timeout
      if (error.name === 'AbortError') {
        console.log('⏱️ Request timed out, will retry on next cron cycle');
        return new Response(
          JSON.stringify({ 
            success: true, // Mark as success to prevent retries
            message: 'Request timed out, will retry on next cron cycle' 
          }),
          { status: 200, headers: { 'Content-Type': 'application/json' } }
        );
      }
      
      throw error;
    }
  } catch (error) {
    console.error('❌ Error in cron job:', error);
    return new Response(
      JSON.stringify({ success: false, error: error.message }),
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    );
  }
});
