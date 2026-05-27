
-- Configure the zip_downloads storage bucket
INSERT INTO storage.buckets (id, name, public)
VALUES ('zip_downloads', 'ZIP Downloads', true)
ON CONFLICT (id) DO UPDATE
SET public = true; -- Make it public for easier access to download links

-- Clear existing policies to avoid conflicts.
-- Supabase storage policies are PostgreSQL RLS policies on storage.objects.
DROP POLICY IF EXISTS "Authenticated users can read ZIPs" ON storage.objects;
DROP POLICY IF EXISTS "Service role can upload ZIPs" ON storage.objects;
DROP POLICY IF EXISTS "Service role can update ZIP objects" ON storage.objects;
DROP POLICY IF EXISTS "Service role can delete ZIP objects" ON storage.objects;
DROP POLICY IF EXISTS "Public can download ZIPs" ON storage.objects;

-- Create policy for authenticated users to read ZIPs
CREATE POLICY "Authenticated users can read ZIPs"
  ON storage.objects
  FOR SELECT
  USING (bucket_id = 'zip_downloads' AND auth.role() = 'authenticated');

-- Create policy for service role to upload ZIPs
CREATE POLICY "Service role can upload ZIPs"
  ON storage.objects
  FOR INSERT
  WITH CHECK (bucket_id = 'zip_downloads');

-- Allow service role to update objects
CREATE POLICY "Service role can update ZIP objects"
  ON storage.objects
  FOR UPDATE
  USING (bucket_id = 'zip_downloads');

-- Allow service role to delete objects
CREATE POLICY "Service role can delete ZIP objects"
  ON storage.objects
  FOR DELETE
  USING (bucket_id = 'zip_downloads');

-- Everyone can read ZIPs if they have the URL
CREATE POLICY "Public can download ZIPs"
  ON storage.objects
  FOR SELECT
  USING (bucket_id = 'zip_downloads');

-- Ensure RLS is enabled on storage objects
ALTER TABLE storage.objects ENABLE ROW LEVEL SECURITY;
