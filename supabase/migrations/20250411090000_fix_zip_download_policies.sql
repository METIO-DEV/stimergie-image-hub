
-- First, make sure the zip-downloads bucket exists
INSERT INTO storage.buckets (id, name, public)
VALUES ('zip-downloads', 'ZIP Downloads', false)
ON CONFLICT (id) DO UPDATE
SET public = false; -- Ensure it's not public for security

-- Remove any existing policies for the zip-downloads bucket to prevent conflicts.
-- Supabase storage policies are PostgreSQL RLS policies on storage.objects.
DROP POLICY IF EXISTS "Authenticated users can download their ZIPs" ON storage.objects;
DROP POLICY IF EXISTS "System can upload ZIPs" ON storage.objects;
DROP POLICY IF EXISTS "System can update ZIP objects" ON storage.objects;
DROP POLICY IF EXISTS "System can delete ZIP objects" ON storage.objects;

-- Allow authenticated users to read from the bucket with signed URLs
CREATE POLICY "Authenticated users can download their ZIPs"
  ON storage.objects
  FOR SELECT
  USING (bucket_id = 'zip-downloads' AND auth.role() = 'authenticated');

-- Allow system (via Edge Functions with service role) to write to the bucket
CREATE POLICY "System can upload ZIPs"
  ON storage.objects
  FOR INSERT
  WITH CHECK (bucket_id = 'zip-downloads');

-- Add a policy for the system to update objects in the bucket (for signed URLs)
CREATE POLICY "System can update ZIP objects"
  ON storage.objects
  FOR UPDATE
  USING (bucket_id = 'zip-downloads');

-- Add a policy for system to delete objects when they're no longer needed
CREATE POLICY "System can delete ZIP objects"
  ON storage.objects
  FOR DELETE
  USING (bucket_id = 'zip-downloads');

-- Make sure we enable RLS on the objects table if it's not already enabled
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;
