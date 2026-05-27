import { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { MasonryGrid } from '@/components/gallery/masonry/MasonryGrid';
import { Skeleton } from '@/components/ui/skeleton';
import { supabase } from '@/integrations/supabase/client';
import { useQuery } from '@tanstack/react-query';
import { Download, ArrowLeft } from 'lucide-react';
import JSZip from 'jszip';
import { saveAs } from 'file-saver';
import { Image } from '@/utils/image/types';
import { useToast } from '@/hooks/use-toast';
import { Json } from '@/integrations/supabase/types';
import { generateDisplayImageUrl, generateDownloadImageHDUrl } from '@/utils/image/imageUrlGenerator';

interface AlbumImage {
  id: number;
  title: string;
  description?: string | null;
  url: string;
  url_miniature?: string | null;
  width?: number | null;
  height?: number | null;
  orientation?: string | null;
  id_projet?: string;
}

interface AlbumData {
  id: string;
  name: string;
  description: string | null;
  access_from: string;
  access_until: string;
  images: AlbumImage[];
  share_key: string;
}

interface RawAlbumData {
  id: string;
  name: string;
  description: string | null;
  access_from: string;
  access_until: string;
  images: Json;
  share_key: string;
}

interface RawImageData {
  id: string | number;
  title?: string;
  url?: string;
  url_miniature?: string | null;
  description?: string | null;
  width?: number | null;
  height?: number | null;
  orientation?: string | null;
  id_projet?: string;
  [key: string]: unknown;
}

const firstNonEmpty = (...values: Array<string | null | undefined>): string => {
  return values.find(value => typeof value === 'string' && value.trim() !== '')?.trim() || '';
};

const SharedAlbum = () => {
  const params = useParams<{ albumKey?: string; shareKey?: string }>();
  const navigate = useNavigate();
  const [isDownloading, setIsDownloading] = useState(false);
  const { toast } = useToast();
  const [folderNames, setFolderNames] = useState<Record<string, string>>({});
  
  // Extract albumKey from multiple sources with fallback
  const albumKey = params.albumKey ?? params.shareKey ?? window.location.pathname.split('/').filter(Boolean).pop() ?? '';
  
  useEffect(() => {
    console.log('useParams():', params);
    console.log('pathname:', window.location.pathname, 'derived albumKey:', albumKey);
  }, [params, albumKey]);
  
  useEffect(() => {
    const currentHostname = window.location.hostname;
    console.log('Current hostname:', currentHostname);
    
    const isPreview = currentHostname.includes('lovable.app');
    const isAllowedHost = ['stimergie.fr', 'www.stimergie.fr', 'localhost', '127.0.0.1'].includes(currentHostname);

    // Only enforce redirect on unknown hosts in production (not in preview)
    if (albumKey && !isPreview && !isAllowedHost) {
      const redirectUrl = `https://www.stimergie.fr/shared-album/${albumKey}`;
      console.log('Redirecting to:', redirectUrl);
      window.location.href = redirectUrl;
    }
  }, [albumKey]);
  
  const fetchAlbumData = async (): Promise<AlbumData> => {
    if (!albumKey) {
      console.error('No album key provided for fetching data');
      throw new Error('Album key is required');
    }
    
    console.log('Fetching album with share key:', albumKey);
    
    const { data, error } = await supabase.rpc('get_album_by_share_key', {
      share_key_param: albumKey
    });
    
    if (error) {
      console.error('Error fetching album:', error);
      throw error;
    }
    
    if (!data || !Array.isArray(data) || data.length === 0) {
      console.error('No album found with key:', albumKey);
      throw new Error('Album not found or expired');
    }
    
    console.log('Album data received:', data);
    
    const rawData = data[0] as RawAlbumData;
    
    let processedImages: AlbumImage[] = [];
    if (rawData.images && typeof rawData.images === 'object') {
      const imageArray = Array.isArray(rawData.images) ? rawData.images : [rawData.images];
      
      processedImages = imageArray.map((img: Json) => {
        const imgData = img as unknown as RawImageData;
        
        const id = typeof imgData.id === 'number' 
          ? imgData.id 
          : parseInt(String(imgData.id || '0'), 10) || 0;
        
        return {
          id: id,
          title: String(imgData.title || ''),
          url: String(imgData.url || ''),
          url_miniature: typeof imgData.url_miniature === 'string' ? imgData.url_miniature : null,
          description: imgData.description as string | null,
          width: typeof imgData.width === 'number' ? imgData.width : null,
          height: typeof imgData.height === 'number' ? imgData.height : null,
          orientation: imgData.orientation as string | null,
          id_projet: imgData.id_projet as string | undefined
        };
      });
    } else {
      console.warn('Album images property is not a valid array:', rawData.images);
    }
    
    console.log('Processed images:', processedImages);
    
    return {
      id: rawData.id,
      name: rawData.name,
      description: rawData.description,
      access_from: rawData.access_from,
      access_until: rawData.access_until,
      images: processedImages,
      share_key: rawData.share_key
    };
  };
  
  const { data: album, isLoading, isError, error } = useQuery({
    queryKey: ['shared-album', albumKey],
    queryFn: fetchAlbumData,
    retry: 1,
    enabled: !!albumKey
  });

  useEffect(() => {
    if (error) {
      console.error('Album query error:', error);
    }
    
    const fetchFolderNames = async () => {
      if (!album || !album.images || !Array.isArray(album.images) || album.images.length === 0) return;
      
      const projectIds = Array.from(
        new Set(
          album.images
            .filter(img => img.id_projet)
            .map(img => img.id_projet)
        )
      );
      
      if (projectIds.length === 0) return;
      
      try {
        const { data, error } = await supabase
          .from('projets')
          .select('id, nom_dossier')
          .in('id', projectIds as string[]);
          
        if (error) throw error;
        
        const folderMap: Record<string, string> = {};
        data.forEach(project => {
          folderMap[project.id] = project.nom_dossier || '';
        });
        
        setFolderNames(folderMap);
        console.log('Folder names fetched:', folderMap);
      } catch (err) {
        console.error('Error fetching folder names:', err);
      }
    };
    
    fetchFolderNames();
  }, [album, error]);
  
  const downloadAllImages = async () => {
    if (!album || !album.images || !Array.isArray(album.images) || album.images.length === 0) return;
    
    setIsDownloading(true);
    toast({
      title: "Préparation du téléchargement",
      description: "Nous préparons votre téléchargement...",
    });
    
    try {
      const zip = new JSZip();
      
      // Pour chaque image, nous allons maintenant utiliser le format HD direct
      const fetchPromises = album.images.map(async (image: AlbumImage, index: number) => {
        try {
          // Récupère le nom du dossier depuis folderNames si l'image a un id_projet
          const folderName = image.id_projet ? folderNames[image.id_projet] || '' : '';
          
          // Si on a un nom de dossier, on peut construire l'URL HD directe
          let downloadUrl = image.url; // URL par défaut
          
          if (folderName && image.title) {
            downloadUrl = generateDownloadImageHDUrl(folderName, image.title);
          }
          
          const response = await fetch(downloadUrl);
          if (!response.ok) throw new Error(`Failed to fetch image: ${response.statusText}`);
          
          const blob = await response.blob();
          const fileName = image.title ? `${image.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.jpg` : `image-${index + 1}.jpg`;
          zip.file(fileName, blob);
          
          return true;
        } catch (err) {
          console.error(`Failed to add image ${index} to zip:`, err);
          return false;
        }
      });
      
      await Promise.all(fetchPromises);
      
      const content = await zip.generateAsync({ type: 'blob' });
      
      const albumName = album.name.replace(/[^a-z0-9]/gi, '_').toLowerCase();
      saveAs(content, `album-${albumName}.zip`);
      
      toast({
        title: "Téléchargement prêt",
        description: "Votre album a été téléchargé avec succès."
      });
    } catch (err) {
      console.error('Error downloading album:', err);
      toast({
        variant: "destructive",
        title: "Erreur de téléchargement",
        description: "Une erreur est survenue lors du téléchargement de l'album."
      });
    } finally {
      setIsDownloading(false);
    }
  };
  
  const formatImagesForGrid = (): Image[] => {
    if (!album || !album.images || !Array.isArray(album.images)) return [];
    
    return album.images.map((image: AlbumImage) => {
      const folderName = image.id_projet ? folderNames[image.id_projet] || '' : '';
      const generatedDisplayUrl = folderName && image.title ? generateDisplayImageUrl(folderName, image.title) : '';
      const generatedDownloadUrl = folderName && image.title ? generateDownloadImageHDUrl(folderName, image.title) : '';
      const display_url = firstNonEmpty(image.url_miniature, generatedDisplayUrl, image.url, '/placeholder.svg');
      const download_url = firstNonEmpty(generatedDownloadUrl, image.url, display_url);
      
      // Pour l'URL de téléchargement, on utilise simplement l'URL fournie
      // ImageCard gérera la transformation si nécessaire
      return {
        id: image.id.toString(),
        src: display_url,
        display_url: display_url,
        download_url,
        download_url_sd: display_url,
        url: image.url, // URL originale
        alt: image.title || 'Image partagée',
        title: image.title || 'Sans titre',
        author: 'Album partagé',
        orientation: image.orientation || 'landscape',
        width: image.width || undefined,
        height: image.height || undefined,
        id_projet: image.id_projet
      } as Image;
    });
  };
  
  if (!albumKey) {
    return (
      <div className="container max-w-7xl mx-auto py-16 px-4 text-center">
        <h1 className="text-2xl font-bold mb-4">Album non trouvé</h1>
        <p className="mb-8">Le lien que vous avez utilisé n'est pas valide.</p>
        <Button onClick={() => navigate('/')}>Retour à l'accueil</Button>
      </div>
    );
  }
  
  if (isLoading) {
    return (
      <div className="container max-w-7xl mx-auto py-16 px-4">
        <Skeleton className="h-8 w-1/3 mb-2" />
        <Skeleton className="h-4 w-1/4 mb-8" />
        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          {[...Array(6)].map((_, i) => (
            <Skeleton key={i} className="h-64 w-full" />
          ))}
        </div>
      </div>
    );
  }
  
  if (isError || !album) {
    console.error('Error displaying album:', error);
    
    return (
      <div className="container max-w-7xl mx-auto py-16 px-4 text-center">
        <h1 className="text-2xl font-bold mb-4">Album non trouvé ou expiré</h1>
        <p className="mb-8">Le lien de partage que vous avez utilisé n'est plus valide ou a expiré.</p>
        <Button onClick={() => navigate('/')}>Retour à l'accueil</Button>
      </div>
    );
  }
  
  const now = new Date();
  const accessFrom = new Date(album?.access_from || '');
  const accessUntil = new Date(album?.access_until || '');
  const isAccessible = now >= accessFrom && now <= accessUntil;
  
  if (!isAccessible) {
    return (
      <div className="container max-w-7xl mx-auto py-16 px-4 text-center">
        <h1 className="text-2xl font-bold mb-4">Album expiré</h1>
        <p className="mb-8">
          Cet album était disponible du {accessFrom.toLocaleDateString()} au {accessUntil.toLocaleDateString()}, mais n'est plus accessible aujourd'hui.
        </p>
        <Button onClick={() => navigate('/')}>Retour à l'accueil</Button>
      </div>
    );
  }
  
  const accessFromFormatted = album?.access_from ? new Date(album.access_from).toLocaleDateString('fr-FR') : '';
  const accessUntilFormatted = album?.access_until ? new Date(album.access_until).toLocaleDateString('fr-FR') : '';
  
  const shareUrl = `https://www.stimergie.fr/shared-album/${album?.share_key}`;
  
  return (
    <div className="min-h-screen bg-background flex flex-col">
      <header className="border-b sticky top-0 bg-background z-10">
        <div className="container max-w-7xl mx-auto py-4 px-4 flex justify-between items-center">
          <Button variant="ghost" className="p-2" onClick={() => navigate('/')}>
            <ArrowLeft className="h-4 w-4 mr-2" />
            Retour
          </Button>
          <div className="flex gap-2">
            <Button
              onClick={downloadAllImages}
              disabled={isDownloading}
            >
              <Download className="h-4 w-4 mr-2" />
              {isDownloading ? 'Préparation...' : 'Télécharger tout'}
            </Button>
          </div>
        </div>
      </header>
      
      <main className="flex-grow container max-w-7xl mx-auto py-8 px-4">
        <div className="mb-12">
          <h1 className="text-3xl font-bold mb-2">{album?.name}</h1>
          {album?.description && (
            <p className="text-muted-foreground mb-2">{album.description}</p>
          )}
          <p className="text-sm text-muted-foreground">
            Accessible du {accessFromFormatted} au {accessUntilFormatted}
          </p>
        </div>
        
        <MasonryGrid
          images={formatImagesForGrid()}
          isLoading={isLoading}
          disableSelection={true}
        />
      </main>
      
      <footer className="border-t py-6">
        <div className="container max-w-7xl mx-auto px-4 text-sm text-muted-foreground text-center">
          © {new Date().getFullYear()} - Album partagé via Stimergie
        </div>
      </footer>
    </div>
  );
};

export default SharedAlbum;
