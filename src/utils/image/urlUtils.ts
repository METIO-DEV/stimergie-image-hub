
/**
 * URL related utility functions
 */

// Available CORS proxies for rotation
const PROXIES = [
  // Remove all proxies - direct access to stimergie.fr
  ''  // Empty string means "no proxy" - direct access
];

// Cache for best proxies
const bestProxyCache = new Map<string, string>();

/**
 * Gets a URL without proxies to access stimergie.fr directly
 */
export function getProxiedUrl(url: string, forceProxy: boolean = false): string {
  // Si l'URL est vide, retourner une chaîne vide
  if (!url) return '';

  // Don't proxy if not needed
  if (!forceProxy && (url.includes(window.location.hostname) || url.startsWith('blob:') || url.startsWith('data:'))) {
    return url;
  }
  
  // Return the direct URL without any proxy since stimergie.fr should have proper CORS headers
  return url;
}

/**
 * Updates the best proxy for a domain based on successful fetches
 * No longer needed but kept for compatibility
 */
export function updateBestProxy(domain: string, proxyUrl: string): void {
  // No proxy selection needed anymore
}

/**
 * Extract domain from URL
 */
function extractDomain(url: string): string {
  try {
    const hostname = new URL(url).hostname;
    const parts = hostname.split('.');
    if (parts.length > 2) {
      return parts.slice(-2).join('.');
    }
    return hostname;
  } catch (e) {
    return url;
  }
}

/**
 * Generate a placeholder URL for an image
 * @param url Original image URL
 * @param width Width of the placeholder
 * @returns A URL for a small placeholder version of the image
 */
export function getPlaceholderUrl(originalUrl: string | null, size: number = 20): string {
  return `/placeholder.svg?size=${size}`;
}

/**
 * Check if the image is likely to be a vector format (SVG)
 */
export function isVectorImage(url: string | null | undefined): boolean {
  return url && url.toLowerCase().endsWith('.svg');
}

/**
 * Vérifie et valide une URL d'image
 * @param url URL à vérifier
 * @returns URL originale ou URL corrigée si valide, null sinon
 */
export function validateImageUrl(url: string | null | undefined): string | null {
  if (!url) return null;
  
  // Rejeter les URLs SVG et les placeholders
  if (url.includes('data:image/svg+xml') || url.includes('placeholder.svg') || url.includes('placeholder.png')) {
    console.warn('URL rejetée (placeholder):', url.substring(0, 50) + '...');
    return null;
  }
  
  // Vérifier si c'est déjà une URL valide
  try {
    new URL(url);
    
    // S'assurer que l'URL se termine par une extension d'image valide
    const urlLower = url.toLowerCase();
    const isPng = urlLower.endsWith('.png');
    const isJpg = urlLower.endsWith('.jpg') || urlLower.endsWith('.jpeg');
    
    // Vérifier si c'est une URL stimergie qui doit suivre le format spécifié
    if (url.includes('stimergie.fr/photos')) {
      // Ajouter l'extension si nécessaire
      if (url.includes('/PNG/') && !isPng) {
        return `${url}.png`;
      } 
      else if (!url.includes('/PNG/') && !isJpg) {
        return `${url}.jpg`;
      }
    }
    
    // URL valide
    return url;
  } catch (e) {
    console.warn('URL invalide:', url);
    return null;
  }
}

/**
 * Affiche des informations de débogage sur une URL d'image
 */
export function debugImageUrl(url: string): void {
  console.log('Image URL Debug:', {
    url,
    isValid: Boolean(url) && url.startsWith('http'),
    length: url?.length || 0,
    containsSpecialChars: /[^a-zA-Z0-9._/:-]/.test(url),
    domain: url ? extractDomain(url) : 'none',
    isPNG: url.toLowerCase().endsWith('.png'),
    isJPG: url.toLowerCase().endsWith('.jpg') || url.toLowerCase().endsWith('.jpeg'),
    isStimergieFormat: url.includes('stimergie.fr/photos')
  });
}
