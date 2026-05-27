
import { useState, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import { Card, CardContent } from '@/components/ui/card';
import { supabase } from '@/integrations/supabase/client';
import { File, Search, Upload } from 'lucide-react';
import { useLocation } from 'react-router-dom';
import { parseTagsString } from '@/utils/imageUtils';
import { generateDisplayImageUrl } from '@/utils/image/imageUrlGenerator';

interface ImageSelectorProps {
  selectedImage: string | null;
  onSelectImage: (url: string | null) => void;
}

export function ImageSelector({ selectedImage, onSelectImage }: ImageSelectorProps) {
  const [activeTab, setActiveTab] = useState<string>('existing');
  const [imageUrl, setImageUrl] = useState<string>('');
  const [file, setFile] = useState<File | null>(null);
  const [isUploading, setIsUploading] = useState(false);
  const [searchQuery, setSearchQuery] = useState('');
  const [galleryImages, setGalleryImages] = useState<Array<{ id: number; url: string; title: string; folder?: string }>>([]);
  const [isLoading, setIsLoading] = useState(false);
  const location = useLocation();
  const isResourcesPage = location.pathname === '/resources';

  useEffect(() => {
    // Initialize the image URL if a selected image is provided
    if (selectedImage) {
      setImageUrl(selectedImage);
    }
  }, [selectedImage]);

  useEffect(() => {
    fetchImages();
  }, [searchQuery, isResourcesPage]);

  const fetchImages = async () => {
    setIsLoading(true);
    try {
      let query = supabase
        .from('images')
        .select('id, url, title, url_miniature, tags, id_projet, projets!id_projet (nom_dossier)')
        .order('created_at', { ascending: false })
        .limit(20);

      if (searchQuery) {
        // Search in both title and tags
        query = query.or(`title.ilike.%${searchQuery}%, tags.ilike.%${searchQuery}%`);
      }

      const { data, error } = await query;
      
      if (error) {
        console.error('Error fetching images:', error);
        return;
      }
      
      const processedData = data?.map(img => {
        const folderName = img.projets?.nom_dossier || "";
        const imageTitle = img.title || `image-${img.id}`;
        
        // Generate display URL using stimergie.fr
        const displayUrl = generateDisplayImageUrl(folderName, imageTitle);
        
        return {
          id: img.id,
          url: displayUrl,
          title: img.title,
          folder: folderName
        };
      }) || [];
      
      setGalleryImages(processedData);
    } catch (error) {
      console.error('Error:', error);
    } finally {
      setIsLoading(false);
    }
  };

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const files = e.target.files;
    if (files && files.length > 0) {
      setFile(files[0]);
    }
  };

  const handleUpload = async () => {
    if (!file) return;

    setIsUploading(true);
    try {
      // In a real app, you would upload to Supabase storage
      // For now, let's just use URL.createObjectURL as a placeholder
      const objectUrl = URL.createObjectURL(file);
      setImageUrl(objectUrl);
      onSelectImage(objectUrl);
    } catch (error) {
      console.error('Upload error:', error);
    } finally {
      setIsUploading(false);
    }
  };

  const handleUrlChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    setImageUrl(e.target.value);
  };

  const handleUrlSubmit = () => {
    if (imageUrl.trim()) {
      onSelectImage(imageUrl);
    }
  };

  const handleSelectGalleryImage = (url: string) => {
    setImageUrl(url);
    onSelectImage(url);
  };

  return (
    <div className="space-y-4">
      {selectedImage && (
        <div className="mb-4">
          <p className="text-sm font-medium mb-2">Image sélectionnée:</p>
          <div className="relative w-full h-40 bg-gray-100 rounded-md overflow-hidden">
            <img 
              src={selectedImage} 
              alt="Selected" 
              className="w-full h-full object-cover"
            />
            <Button
              variant="destructive"
              size="sm"
              className="absolute top-2 right-2"
              onClick={() => onSelectImage(null)}
            >
              Supprimer
            </Button>
          </div>
        </div>
      )}

      <Tabs defaultValue="existing" onValueChange={setActiveTab}>
        <TabsList className="grid w-full grid-cols-3">
          <TabsTrigger value="existing">
            <Search className="mr-2 h-4 w-4" />
            Bibliothèque
          </TabsTrigger>
          <TabsTrigger value="url">
            <File className="mr-2 h-4 w-4" />
            URL
          </TabsTrigger>
          <TabsTrigger value="upload">
            <Upload className="mr-2 h-4 w-4" />
            Télécharger
          </TabsTrigger>
        </TabsList>

        <TabsContent value="existing">
          <Card>
            <CardContent className="pt-4">
              <div className="mb-4">
                <Input
                  type="text"
                  placeholder="Rechercher par titre ou tags..."
                  value={searchQuery}
                  onChange={(e) => setSearchQuery(e.target.value)}
                />
              </div>
              
              {isLoading ? (
                <div className="py-4 text-center">Chargement...</div>
              ) : galleryImages.length === 0 ? (
                <div className="py-4 text-center">Aucune image trouvée</div>
              ) : (
                <div className="grid grid-cols-2 sm:grid-cols-3 gap-4">
                  {galleryImages.map((image) => (
                    <div 
                      key={image.id}
                      className={`
                        relative aspect-square rounded-md overflow-hidden border-2 cursor-pointer
                        ${selectedImage === image.url ? 'border-primary' : 'border-transparent'}
                        hover:opacity-90 transition-opacity
                      `}
                      onClick={() => handleSelectGalleryImage(image.url)}
                    >
                      <img 
                        src={image.url} 
                        alt={image.title} 
                        className="w-full h-full object-cover"
                        onError={(e) => {
                          console.error('Image load error:', e);
                          const imgElement = e.target as HTMLImageElement;
                          imgElement.src = '/placeholder.svg';
                        }}
                      />
                    </div>
                  ))}
                </div>
              )}
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="url">
          <Card>
            <CardContent className="pt-4 space-y-4">
              <Input
                type="url"
                placeholder="https://example.com/image.jpg"
                value={imageUrl}
                onChange={handleUrlChange}
              />
              <Button onClick={handleUrlSubmit} className="w-full">
                Utiliser cette URL
              </Button>
            </CardContent>
          </Card>
        </TabsContent>

        <TabsContent value="upload">
          <Card>
            <CardContent className="pt-4 space-y-4">
              <Input
                type="file"
                accept="image/*"
                onChange={handleFileChange}
              />
              <Button 
                onClick={handleUpload} 
                disabled={!file || isUploading}
                className="w-full"
              >
                {isUploading ? 'Téléchargement...' : 'Télécharger'}
              </Button>
            </CardContent>
          </Card>
        </TabsContent>
      </Tabs>
    </div>
  );
}
