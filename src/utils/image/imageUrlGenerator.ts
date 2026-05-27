
/**
 * Génère les URLs pour les images sur le serveur Stimergie
 */

const STIMERGIE_PHOTOS_BASE_URL = 'https://www.stimergie.fr/photos';

function stripImageExtension(imageTitle: string): string {
  return imageTitle.replace(/\.(jpg|jpeg|png|webp)$/i, '').trim();
}

function encodePathPreservingSlashes(path: string): string {
  return path
    .trim()
    .split('/')
    .filter(Boolean)
    .map(segment => encodeURIComponent(segment.trim()))
    .join('/');
}

function buildStimergieImageUrl(folderName: string, imageTitle: string, qualityFolder?: 'JPG'): string {
  if (!folderName || !imageTitle) {
    return '';
  }

  const encodedFolder = encodePathPreservingSlashes(folderName);
  const encodedTitle = encodeURIComponent(stripImageExtension(imageTitle));
  const qualitySegment = qualityFolder ? `/${qualityFolder}` : '';

  return `${STIMERGIE_PHOTOS_BASE_URL}/${encodedFolder}${qualitySegment}/${encodedTitle}.jpg`;
}

/**
 * Génère l'URL d'affichage pour une image (format JPG pour affichage)
 * Format: https://www.stimergie.fr/photos/[nom du dossier]/JPG/[titre].jpg
 */
export function generateDisplayImageUrl(folderName: string, imageTitle: string): string {
  if (!folderName || !imageTitle) {
    console.warn(`Nom de dossier ou titre d'image manquant pour générer l'URL d'affichage: ${folderName || 'dossier manquant'} / ${imageTitle || 'titre manquant'}`);
    return '';
  }

  return buildStimergieImageUrl(folderName, imageTitle, 'JPG');
}

/**
 * Génère l'URL de téléchargement pour une image en qualité normale (JPG)
 * Format: https://www.stimergie.fr/photos/[nom_dossier]/JPG/[titre].jpg
 */
export function generateDownloadImageSDUrl(folderName: string, imageTitle: string): string {
  if (!folderName || !imageTitle) {
    console.warn(`Nom de dossier ou titre d'image manquant pour générer l'URL de téléchargement SD: ${folderName || 'dossier manquant'} / ${imageTitle || 'titre manquant'}`);
    return '';
  }

  return buildStimergieImageUrl(folderName, imageTitle, 'JPG');
}

/**
 * Génère l'URL de téléchargement HD pour une image (format original)
 * Format: https://www.stimergie.fr/photos/[nom_dossier]/[titre].jpg
 * IMPORTANT: Pas de segment "/JPG" dans l'URL HD
 */
export function generateDownloadImageHDUrl(folderName: string, imageTitle: string): string {
  if (!folderName || !imageTitle) {
    console.warn(`Nom de dossier ou titre d'image manquant pour générer l'URL de téléchargement HD: ${folderName || 'dossier manquant'} / ${imageTitle || 'titre manquant'}`);
    return '';
  }

  return buildStimergieImageUrl(folderName, imageTitle);
}

/**
 * Pour rétrocompatibilité - Alias de generateDownloadImageHDUrl
 * @deprecated Utiliser generateDownloadImageHDUrl à la place
 */
export function generateDownloadImageUrl(folderName: string, imageTitle: string): string {
  return generateDownloadImageHDUrl(folderName, imageTitle);
}

/**
 * Vérifie si une URL stimergie.fr est accessible, sinon retourne une URL alternative
 */
export async function validateAndFixImageUrl(url: string): Promise<string> {
  if (!url) return '';
  
  try {
    // Essayer de vérifier si l'URL est accessible
    const response = await fetch(url, { 
      method: 'HEAD', 
      mode: 'no-cors',
      cache: 'force-cache'
    });
    
    // Si l'URL semble fonctionner, la retourner
    return url;
  } catch (error) {
    console.warn(`URL inaccessible: ${url}`, error);
    
    // Essayer une alternative sans proxy
    if (url.includes('corsproxy.io')) {
      const decodedUrl = decodeURIComponent(url.split('corsproxy.io/?')[1]);
      return decodedUrl;
    }
    
    // En dernier recours, utiliser une image de remplacement
    return '/placeholder.svg';
  }
}

/**
 * Décode l'URL si nécessaire (ex: URL avec proxy)
 */
export function decodeUrlIfNeeded(url: string): string {
  if (!url) return '';
  
  // Si l'URL utilise un proxy, extraire l'URL originale
  if (url.includes('corsproxy.io')) {
    return decodeURIComponent(url.split('corsproxy.io/?')[1]);
  }
  
  return url;
}
