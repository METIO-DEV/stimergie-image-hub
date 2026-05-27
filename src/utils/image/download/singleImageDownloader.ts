import { toast } from 'sonner';
import { transformToHDUrl } from './networkUtils';

export type ImageDownloadFormat = 'jpg' | 'png' | 'auto';

/**
 * Downloads an image from a given URL and saves it to the user's device
 */
export async function downloadImage(
  url: string, 
  filename: string, 
  format: ImageDownloadFormat = 'auto',
  isHD: boolean = false
): Promise<void> {
  if (!url) {
    console.error('Download failed: URL is empty');
    toast.error('Échec du téléchargement', {
      description: 'L\'URL de l\'image est manquante.'
    });
    throw new Error('URL is empty');
  }

  console.log(`[downloadImage] Downloading image from URL: ${url}`);
  console.log(`[downloadImage] URL contains '/JPG/': ${url.includes('/JPG/')}`);
  console.log(`[downloadImage] HD mode: ${isHD}`);
  
  // Si c'est un téléchargement HD, transformer l'URL en supprimant /JPG/
  let downloadUrl = isHD ? transformToHDUrl(url) : url;
  
  // Encoder correctement l'URL pour gérer les espaces et caractères spéciaux
  try {
    const urlObj = new URL(downloadUrl);
    // Encoder le pathname seulement (pas le protocole et domaine)
    urlObj.pathname = urlObj.pathname.split('/').map(part => encodeURIComponent(decodeURIComponent(part))).join('/');
    downloadUrl = urlObj.toString();
  } catch (e) {
    console.warn('[downloadImage] URL encoding failed, using original URL', e);
  }
  
  console.log(`[downloadImage] Final download URL: ${downloadUrl}`);
  console.log(`[downloadImage] Final URL contains '/JPG/': ${downloadUrl.includes('/JPG/')}`);
  console.log(`[downloadImage] Saving as filename: ${filename}`);
  
  let fileExtension = '';
  if (format === 'jpg') {
    fileExtension = '.jpg';
  } else if (format === 'png') {
    fileExtension = '.png';
  } else {
    fileExtension = downloadUrl.toLowerCase().includes('.png') ? '.png' : '.jpg';
  }

  const filenameWithExtension = filename.endsWith(fileExtension)
    ? filename
    : filename.replace(/\.[^.]+$/, '') + fileExtension;

  const link = document.createElement('a');
  link.href = downloadUrl;
  link.download = filenameWithExtension;
  link.target = '_blank';
  link.rel = 'noopener noreferrer';
  link.style.display = 'none';
  document.body.appendChild(link);
  link.click();
  document.body.removeChild(link);

  toast.info('Téléchargement direct ouvert');
}
