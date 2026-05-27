
// This function will be called daily to cache images from the server

// First, we import the necessary modules
const FUNCTION_ENDPOINT = Deno.env.get('PUBLIC_URL') || Deno.env.get('SUPABASE_URL')
const ANON_KEY = Deno.env.get('SUPABASE_ANON_KEY') || ''

// Handle the cron job request
Deno.serve(async (_req) => {
  try {
    if (!FUNCTION_ENDPOINT || !ANON_KEY) {
      throw new Error('Missing PUBLIC_URL/SUPABASE_URL or SUPABASE_ANON_KEY')
    }

    console.log('Starting daily image cache refresh job')
    
    // Call the cache images function
    const response = await fetch(`${FUNCTION_ENDPOINT}/functions/v1/cache-images`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${ANON_KEY}`
      },
      body: JSON.stringify({ immediate: true })
    })
    
    if (!response.ok) {
      const errorText = await response.text()
      throw new Error(`Failed to trigger cache refresh: ${response.status} - ${errorText}`)
    }
    
    const result = await response.json()
    console.log('Daily cache refresh job triggered successfully:', result)
    
    return new Response(
      JSON.stringify({ success: true, message: 'Daily cache refresh job triggered successfully' }),
      { headers: { 'Content-Type': 'application/json' } }
    )
  } catch (error) {
    console.error('Error in cron job:', error)
    return new Response(
      JSON.stringify({ success: false, error: error.message }),
      { status: 500, headers: { 'Content-Type': 'application/json' } }
    )
  }
})
