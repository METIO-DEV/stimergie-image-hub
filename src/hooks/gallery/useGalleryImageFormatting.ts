
import { useCallback } from 'react';
import { Image } from '@/utils/image/types';
import { generateDisplayImageUrl, generateDownloadImageHDUrl, generateDownloadImageSDUrl } from '@/utils/image/imageUrlGenerator';
import { parseTagsString } from '@/utils/imageUtils';

interface RawGalleryImage {
  id?: string | number | null;
  title?: string | null;
  display_url?: string | null;
  url_miniature?: string | null;
  url?: string | null;
  download_url?: string | null;
  download_url_sd?: string | null;
  width?: string | number | null;
  height?: string | number | null;
  orientation?: string | null;
  tags?: string[] | string | null;
  created_by?: string | null;
  created_at?: string | null;
  description?: string | null;
  id_projet?: string | null;
  projets?: {
    nom_projet?: string | null;
    nom_dossier?: string | null;
    clients?: {
      id?: string | null;
      nom?: string | null;
    } | null;
  } | null;
  image_shared_clients?: Image['image_shared_clients'] | null;
}

const firstNonEmpty = (...values: Array<string | null | undefined>): string => {
  return values.find(value => typeof value === 'string' && value.trim() !== '')?.trim() || '';
};

/**
 * Hook for handling image formatting and transformation logic
 */
export const useGalleryImageFormatting = () => {
  /**
   * Formats raw image data from the API for display in the gallery grid
   */
  const formatImagesForGrid = useCallback((images: RawGalleryImage[] = []) => {
    return images.map(image => {
      // Garantir que toutes les images ont un ID au format string
      const id = image.id ? String(image.id) : `img-${Math.random().toString(36).substring(2, 9)}`;
      const title = image.title || "Sans titre";
      const folderName = image.projets?.nom_dossier || '';
      const generatedDisplayUrl = folderName && title ? generateDisplayImageUrl(folderName, title) : '';
      const generatedSDUrl = folderName && title ? generateDownloadImageSDUrl(folderName, title) : '';
      const generatedHDUrl = folderName && title ? generateDownloadImageHDUrl(folderName, title) : '';
      
      // URLs pour affichage et téléchargement
      const srcUrl = firstNonEmpty(image.display_url, image.url_miniature, generatedDisplayUrl, image.url, '/placeholder.svg');
      const downloadUrl = firstNonEmpty(image.download_url, image.url, generatedHDUrl, srcUrl);
      const downloadUrlSd = firstNonEmpty(image.download_url_sd, image.url_miniature, generatedSDUrl, srcUrl);
      
      // Dimensions et orientation
      const width = Number(image.width) || 0;
      const height = Number(image.height) || 0;
      
      let orientation = image.orientation || 'paysage';
      if (width > 0 && height > 0) {
        if (width > height) {
          orientation = 'paysage';
        } else if (height > width) {
          orientation = 'portrait';
        } else {
          orientation = 'carré';
        }
      }
      
      // Traitement spécifique pour les tags au format texte avec séparateurs virgule
      let tags = [];
      if (image.tags) {
        if (typeof image.tags === 'string') {
          tags = parseTagsString(image.tags);
        } else if (Array.isArray(image.tags)) {
          // Si c'est déjà un tableau, on le garde tel quel
          tags = image.tags;
        } else {
          // Fallback pour les autres cas
          tags = [];
        }
      }
      
      return {
        id: id,
        src: srcUrl,
        display_url: srcUrl,
        download_url: downloadUrl,
        download_url_sd: downloadUrlSd,
        alt: title || "Image sans titre",
        title,
        author: image.created_by || 'Utilisateur',
        tags: tags,
        orientation: orientation,
        width: width,
        height: height,
        created_at: image.created_at || new Date().toISOString(),
        description: image.description || '',
        url_miniature: srcUrl,
        url: downloadUrl,
        projets: image.projets || null,
        id_projet: image.id_projet || null,
        image_shared_clients: image.image_shared_clients || null
      } as Image;
    });
  }, []);

  return { formatImagesForGrid };
};
