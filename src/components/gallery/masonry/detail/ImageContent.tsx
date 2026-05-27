import React, { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Download, Folder, User } from 'lucide-react';
import { toast } from 'sonner';
import { downloadImage } from '@/utils/image/download';
import { generateDownloadImageHDUrl, generateDownloadImageSDUrl } from '@/utils/image/imageUrlGenerator';
import { parseTagsString } from '@/utils/imageUtils';
import { TagsEditor } from './TagsEditor';
import { ImageSharingManager } from '@/components/images/ImageSharingManager';
import { useAuth } from '@/context/AuthContext';
import { useNavigate } from 'react-router-dom';
import { fetchImageAsBlob } from '@/utils/image/fetcher';
import { supabase } from '@/integrations/supabase/client';
import type { Image as GalleryImage } from '@/utils/image/types';

interface SharedClient {
  clients?: {
    nom?: string;
  };
}

type ImageContentImage = Omit<Partial<GalleryImage>, 'id' | 'tags' | 'image_shared_clients'> & {
  id?: string | number;
  tags?: string[] | string | null;
  folder_name?: string | null;
  copyright?: string | null;
  image_shared_clients?: SharedClient[];
};

interface ImageContentProps {
  image: ImageContentImage;
  imageDimensions: { width: number; height: number };
  isFullPage: boolean;
  onImageLoad?: (e: React.SyntheticEvent<HTMLImageElement>) => void;
}

export const ImageContent = ({ 
  image, 
  imageDimensions, 
  isFullPage,
  onImageLoad 
}: ImageContentProps) => {
  const [isDownloading, setIsDownloading] = useState(false);
  const [imageError, setImageError] = useState(false);
  const [currentTags, setCurrentTags] = useState(image?.tags);
  const [folderName, setFolderName] = useState<string | null>(image?.projets?.nom_dossier ?? null);
  const [clientInfo, setClientInfo] = useState<{ id: string; nom: string } | null>(
    image?.projets?.clients ? { id: image.projets.clients.id, nom: image.projets.clients.nom } : null
  );
  const { userRole, isAdmin } = useAuth();
  const navigate = useNavigate();

  // Fallback to fetch project data if not embedded
  useEffect(() => {
    const fetchProjectData = async () => {
      try {
        if ((!folderName || !clientInfo) && image?.id_projet) {
          const { data, error } = await supabase
            .from('projets')
            .select('nom_dossier, clients:id_client(id, nom)')
            .eq('id', image.id_projet)
            .maybeSingle();
          
          if (!error && data) {
            if (data.nom_dossier && !folderName) setFolderName(data.nom_dossier);
            if (data.clients && !clientInfo) setClientInfo({ id: data.clients.id, nom: data.clients.nom });
          }
        } else if ((!folderName || !clientInfo) && image?.id) {
          const { data, error } = await supabase
            .from('images')
            .select('id_projet, projets:projets(nom_dossier, clients:id_client(id, nom))')
            .eq('id', image.id)
            .maybeSingle();
          
          if (!error && data) {
            if (!folderName) setFolderName(data.projets?.nom_dossier ?? null);
            if (!clientInfo && data.projets?.clients) setClientInfo({ id: data.projets.clients.id, nom: data.projets.clients.nom });
          }
        }
      } catch (err) {
        console.error('Unexpected error fetching project data:', err);
      }
    };
    
    fetchProjectData();
  }, [image?.id_projet, image?.id, folderName, clientInfo]);

  // Get the folder name to display from project
  const getFolderDisplayName = (): string | null => {
    return folderName || image?.projets?.nom_dossier || image?.folder_name || null;
  };

  const handleFolderClick = () => {
    const folderName = getFolderDisplayName();
    if (!folderName) return;
    
    // Navigate to gallery with folder search
    navigate(`/gallery?q=${encodeURIComponent(folderName)}`);
  };

  const handleClientClick = () => {
    const clientId = clientInfo?.id || image?.projets?.clients?.id;
    if (!clientId) return;
    
    // Navigate to gallery filtering by client
    navigate(`/gallery?client=${clientId}`);
  };

  // Process tags to ensure they're always in array format
  const processTags = (tags: unknown): string[] => {
    if (!tags) return [];
    
    if (typeof tags === 'string') {
      return parseTagsString(tags);
    } else if (Array.isArray(tags)) {
      return tags;
    }
    
    return [];
  };
  
  const displayTags = processTags(currentTags);
  
  console.log("ImageContent component - Image tags:", image?.tags);
  console.log("ImageContent component - Current tags:", currentTags);
  console.log("ImageContent component - Processed tags:", displayTags);

  // Pré-charger l'image HD en arrière-plan pour un téléchargement instantané
  useEffect(() => {
    if (image?.folder_name && image?.title) {
      const hdUrl = generateDownloadImageHDUrl(image.folder_name, image.title);
      // Pré-charge en cache silencieusement
      fetchImageAsBlob(hdUrl).catch(() => {
        // Ignore les erreurs de pré-chargement
      });
    }
  }, [image?.folder_name, image?.title]);

  const handleDownload = async (isHD: boolean = false) => {
    let downloadUrl = '';

    try {
      if (isHD) {
        if (folderName && image?.title) {
          downloadUrl = generateDownloadImageHDUrl(folderName, image.title);
        } else if (image?.download_url) {
          downloadUrl = image.download_url.replace('/JPG/', '/');
        } else {
          downloadUrl = image?.display_url || image?.url || image?.url_miniature || image?.src || '';
          downloadUrl = downloadUrl.replace('/JPG/', '/');
        }
      } else if (folderName && image?.title) {
        downloadUrl = generateDownloadImageSDUrl(folderName, image.title);
      } else {
        downloadUrl = image?.display_url || image?.url_miniature || image?.url || image?.src || '';
      }

      if (!downloadUrl) {
        throw new Error('Aucune URL de téléchargement disponible');
      }

      console.log(`Téléchargement ${isHD ? 'HD' : 'SD'} depuis:`, downloadUrl);
      
      setIsDownloading(true);
      const filename = `${(image?.title || 'image')
        .replace(/[^a-z0-9]/gi, '_')
        .toLowerCase()}_${isHD ? 'HD' : 'SD'}.jpg`;

      await downloadImage(downloadUrl, filename, 'jpg', isHD);
      toast.success(`Téléchargement ${isHD ? 'HD' : 'SD'} démarré`);
    } catch (error) {
      console.error('Erreur lors du téléchargement:', error);
      toast.error(`Impossible de télécharger l'image: ${error instanceof Error ? error.message : 'Erreur inconnue'}`);
    } finally {
      setIsDownloading(false);
    }
  };

  const handleImageError = () => {
    setImageError(true);
  };

  const handleTagsUpdated = (newTags: string[]) => {
    setCurrentTags(newTags);
  };

  const imageSrc = imageError 
    ? '/image-not-available.png' 
    : (image?.display_url || image?.url_miniature || image?.src || image?.url || '');

  return (
    <div className="space-y-4">
      <div className="space-y-2">
        <h2 className="text-xl font-bold">{image?.title || 'Sans titre'}</h2>
        
        {/* Display folder name */}
        {getFolderDisplayName() && (
          <button
            onClick={handleFolderClick}
            className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-primary transition-colors cursor-pointer"
          >
            <Folder className="h-3.5 w-3.5" />
            <span className="text-left text-sm">{getFolderDisplayName()}</span>
          </button>
        )}
        
        {/* Display client name */}
        {(clientInfo?.nom || image?.projets?.clients?.nom) && (
          <button
            onClick={handleClientClick}
            className="flex items-center gap-1.5 text-xs text-muted-foreground hover:text-primary transition-colors cursor-pointer"
          >
            <User className="h-3 w-3" />
            <span>{clientInfo?.nom || image.projets.clients.nom}</span>
          </button>
        )}
        
        {/* Download buttons */}
        <div className="flex items-center gap-2 flex-shrink-0 pt-2">
          <Button 
            onClick={() => handleDownload(false)}
            disabled={isDownloading}
            size="sm"
            className="bg-primary text-white hover:bg-primary/90 border-0 font-medium px-4 py-2 min-w-[120px]"
          >
            <Download className="h-4 w-4 mr-2" />
            {isDownloading ? 'Téléchargement...' : 'SD (Web)'}
          </Button>
          <Button 
            onClick={() => handleDownload(true)}
            disabled={isDownloading}
            size="sm"
            className="bg-blue-600 text-white hover:bg-blue-700 border-0 font-medium px-4 py-2 min-w-[140px]"
          >
            <Download className="h-4 w-4 mr-2" />
            {isDownloading ? 'Téléchargement...' : 'HD (Impression)'}
          </Button>
        </div>
      </div>
      
      <div className="relative rounded-md overflow-hidden flex justify-center">
        <div 
          className="relative"
          style={{ 
            overflow: 'hidden', 
            height: 'auto'
          }}
        >
          <img 
            src={imageSrc}
            alt={image?.title || 'Image'} 
            className={`max-w-full ${isFullPage ? 'max-h-[80vh]' : 'max-h-[70vh]'} object-contain`}
            onLoad={onImageLoad}
            onError={handleImageError}
          />
        </div>
      </div>
      
      {/* Éditeur de tags */}
      <TagsEditor 
        imageId={image?.id?.toString() || ''}
        initialTags={displayTags}
        onTagsUpdated={handleTagsUpdated}
      />
      
      {/* Image sharing */}
      <ImageSharingManager 
        imageId={parseInt(image?.id)}
        primaryClientId={image?.projets?.clients?.id}
      />
      
      <div className="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
        <div className="flex flex-wrap gap-4 text-sm text-muted-foreground">
          <div>
            <span className="block text-foreground font-medium">Dimensions</span>
            <span>{imageDimensions.width || image?.width || '–'} × {imageDimensions.height || image?.height || '–'}</span>
          </div>
          {(image?.folder_name || image?.projets?.nom_dossier) && (
            <div>
              <span className="block text-foreground font-medium">Dossier</span>
              <span>{image?.folder_name || image?.projets?.nom_dossier}</span>
            </div>
          )}
          {image?.orientation && (
            <div>
              <span className="block text-foreground font-medium">Orientation</span>
              <span className="capitalize">{image.orientation}</span>
            </div>
          )}
          <div>
            <span className="block text-foreground font-medium">Copyright</span>
            <span>{image?.copyright || 'Stimergie'}</span>
          </div>
          {image?.created_at && (
            <div>
              <span className="block text-foreground font-medium">Date d'ajout</span>
              <span>{new Date(image.created_at).toLocaleDateString('fr-FR')}</span>
            </div>
          )}
        </div>
        
        <div className="space-y-4">
          {/* Show shared clients if any (admin only) */}
          {userRole === 'admin' && image?.image_shared_clients && image.image_shared_clients.length > 0 && (
            <div>
              <span className="block text-foreground font-medium">Également partagé avec</span>
              <p className="text-muted-foreground">
                {image.image_shared_clients.map((shared) => shared.clients?.nom).filter(Boolean).join(', ')}
              </p>
            </div>
          )}
          
          {image?.description && (
            <div>
              <span className="block text-foreground font-medium">Description</span>
              <p className="text-muted-foreground">{image.description}</p>
            </div>
          )}
        </div>
      </div>
    </div>
  );
};
