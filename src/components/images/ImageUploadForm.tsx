import { useState, useRef, ChangeEvent, useEffect, KeyboardEvent, useCallback } from 'react';
import { supabase } from '@/integrations/supabase/client';
import { useAuth } from '@/context/AuthContext';
import {
  Dialog,
  DialogContent,
  DialogHeader,
  DialogTitle,
  DialogFooter,
  DialogDescription
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Upload, X, Loader2, ImagePlus, Tag, PlusCircle } from 'lucide-react';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useToast } from '@/hooks/use-toast';
import { Form, FormControl, FormField, FormItem } from '@/components/ui/form';

interface Project {
  id: string;
  nom_projet: string;
}

interface ImageUploadFormProps {
  isOpen: boolean;
  onClose: () => void;
  onSuccess: () => void;
  userRole?: string;
}

export function ImageUploadForm({ isOpen, onClose, onSuccess, userRole = 'user' }: ImageUploadFormProps) {
  const { user } = useAuth();
  const { toast } = useToast();
  const [title, setTitle] = useState('');
  const [description, setDescription] = useState('');
  const [file, setFile] = useState<File | null>(null);
  const [preview, setPreview] = useState<string | null>(null);
  const [dimensions, setDimensions] = useState<{ width: number; height: number } | null>(null);
  const [orientation, setOrientation] = useState<string>('landscape');
  const [tags, setTags] = useState<string[]>([]);
  const [suggestedTags, setSuggestedTags] = useState<string[]>([]);
  const [uploading, setUploading] = useState(false);
  const [analyzing, setAnalyzing] = useState(false);
  const [projects, setProjects] = useState<Project[]>([]);
  const [selectedProject, setSelectedProject] = useState<string>('');
  const [isLoadingProjects, setIsLoadingProjects] = useState(false);
  const [tagError, setTagError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [newTag, setNewTag] = useState<string>('');
  const tagInputRef = useRef<HTMLInputElement>(null);
  const [autoAnalyzeEnabled, setAutoAnalyzeEnabled] = useState<boolean>(true);

  const fetchProjects = useCallback(async () => {
    if (!user) return;
    
    setIsLoadingProjects(true);
    try {
      let query = supabase
        .from('projets')
        .select('id, nom_projet');
      
      if (userRole === 'admin_client') {
        const { data: profileData, error: profileError } = await supabase
          .from('profiles')
          .select('id_client, client_ids')
          .eq('id', user.id)
          .single();
          
        if (profileError) throw profileError;

        const clientIds = [
          ...(profileData?.id_client ? [profileData.id_client] : []),
          ...(profileData?.client_ids || [])
        ];
        const uniqueClientIds = [...new Set(clientIds)].filter(Boolean);

        if (uniqueClientIds.length === 0) {
          setProjects([]);
          setSelectedProject('');
          return;
        }

        query = query.in('id_client', uniqueClientIds);
      }
      
      const { data, error } = await query.order('nom_projet');
      
      if (error) throw error;
      
      setProjects(data || []);
      setSelectedProject(currentProject => {
        if (data && data.length === 1) {
          return data[0].id;
        }

        if (currentProject && !data?.some(project => project.id === currentProject)) {
          return '';
        }

        return currentProject;
      });
    } catch (error) {
      console.error('Error fetching projects:', error);
      setProjects([]);
    } finally {
      setIsLoadingProjects(false);
    }
  }, [user, userRole]);

  useEffect(() => {
    if (isOpen && user) {
      fetchProjects();
    }
  }, [fetchProjects, isOpen, user]);

  const resetForm = () => {
    setTitle('');
    setDescription('');
    setFile(null);
    setPreview(null);
    setDimensions(null);
    setOrientation('landscape');
    setTags([]);
    setSuggestedTags([]);
    setSelectedProject('');
    setTagError(null);
    setNewTag('');
    if (fileInputRef.current) {
      fileInputRef.current.value = '';
    }
  };

  const handleFileChange = (e: ChangeEvent<HTMLInputElement>) => {
    if (!e.target.files || e.target.files.length === 0) {
      setFile(null);
      setPreview(null);
      setDimensions(null);
      return;
    }

    const selectedFile = e.target.files[0];
    setFile(selectedFile);
    
    const fileName = selectedFile.name.split('.').slice(0, -1).join('.');
    setTitle(fileName);
    
    const previewUrl = URL.createObjectURL(selectedFile);
    setPreview(previewUrl);
    
    const img = new Image();
    img.onload = () => {
      setDimensions({
        width: img.width,
        height: img.height
      });
      
      if (img.width > img.height) {
        setOrientation('landscape');
      } else if (img.height > img.width) {
        setOrientation('portrait');
      } else {
        setOrientation('square');
      }
      
      if (autoAnalyzeEnabled) {
        analyzeImage(selectedFile);
      }
    };
    
    img.src = previewUrl;
  };

  const fileToBase64 = (file: File): Promise<string> => {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.readAsDataURL(file);
      reader.onload = () => {
        if (typeof reader.result === 'string') {
          const base64String = reader.result.split(',')[1];
          resolve(base64String);
        } else {
          reject(new Error('Failed to convert file to base64'));
        }
      };
      reader.onerror = error => reject(error);
    });
  };

  const analyzeImage = async (imageFile: File) => {
    setAnalyzing(true);
    setTagError(null);
    setSuggestedTags([]);
    
    try {
      console.log("Converting image to base64...");
      const base64Data = await fileToBase64(imageFile);
      console.log("Image converted to base64");
      
      const { data, error } = await supabase.functions.invoke('analyze-image-ai', {
        body: { imageBase64: base64Data },
      });

      if (error) {
        console.error("Error from analyze-image-ai function:", error);
        throw new Error(error.message || 'Failed to analyze image');
      }

      const tags = data?.tags;
      console.log("Tags received from AI:", tags);
      
      if (Array.isArray(tags) && tags.length > 0) {
        setSuggestedTags(tags);
        setTags(tags);
      } else {
        console.warn("No tags returned from AI function");
        setTagError("Aucun tag n'a pu être généré. Essayez d'ajouter des tags manuellement.");
        setSuggestedTags(["image", "photo", "média", "visuel", "contenu"]);
      }
    } catch (error) {
      console.error('Error analyzing image:', error);
      setTagError("Erreur lors de l'analyse de l'image. Essayez d'ajouter des tags manuellement.");
      setSuggestedTags(["image", "photo", "média", "visuel", "contenu"]);
      toast({
        variant: "destructive",
        title: "Erreur",
        description: "Impossible d'analyser l'image avec l'IA. Des tags par défaut ont été ajoutés."
      });
    } finally {
      setAnalyzing(false);
    }
  };

  const toggleTag = (tag: string) => {
    if (tags.includes(tag)) {
      setTags(tags.filter(t => t !== tag));
    } else {
      setTags([...tags, tag]);
    }
  };

  const addNewTag = () => {
    const trimmedTag = newTag.trim();
    if (trimmedTag && !tags.includes(trimmedTag)) {
      setTags([...tags, trimmedTag]);
      setNewTag('');
      if (tagInputRef.current) {
        tagInputRef.current.focus();
      }
    }
  };

  const handleTagInputKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      addNewTag();
    }
  };

  const uploadImage = async () => {
    if (!file || !dimensions || !user || !selectedProject) return;
    
    try {
      setUploading(true);
      
      const { error: bucketError } = await supabase.storage.createBucket('images', {
        public: true,
        fileSizeLimit: 10485760,
      });
      
      if (bucketError && bucketError.message !== 'Bucket already exists') {
        throw bucketError;
      }
      
      const fileExt = file.name.split('.').pop();
      const fileName = `${Date.now()}.${fileExt}`;
      const filePath = `${fileName}`;
      
      const { data: uploadData, error: uploadError } = await supabase.storage
        .from('images')
        .upload(filePath, file);
      
      if (uploadError) {
        throw uploadError;
      }
      
      const { data: urlData } = supabase.storage
        .from('images')
        .getPublicUrl(filePath);
      
      const publicUrl = urlData.publicUrl;
      
      const tagsString = tags.length > 0 ? JSON.stringify(tags) : null;
      
      const { error: insertError } = await supabase
        .from('images')
        .insert({
          title: title,
          description: description,
          url: publicUrl,
          width: dimensions.width,
          height: dimensions.height,
          orientation: orientation,
          tags: tagsString,
          created_by: user.id,
          id_projet: selectedProject,
        });
      
      if (insertError) {
        throw insertError;
      }
      
      if (preview) {
        URL.revokeObjectURL(preview);
      }
      
      toast({
        title: "Succès",
        description: "L'image a été téléchargée avec succès"
      });
      
      resetForm();
      onSuccess();
    } catch (error) {
      console.error('Error uploading image:', error);
      toast({
        variant: "destructive",
        title: "Erreur",
        description: "Impossible de télécharger l'image. Veuillez réessayer."
      });
    } finally {
      setUploading(false);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    await uploadImage();
  };

  const handleManualAnalyze = () => {
    if (file) {
      analyzeImage(file);
    }
  };

  const toggleAutoAnalyze = () => {
    setAutoAnalyzeEnabled(!autoAnalyzeEnabled);
  };

  return (
    <Dialog open={isOpen} onOpenChange={(open) => {
      if (!open) {
        onClose();
      }
    }}>
      <DialogContent className="sm:max-w-xl max-h-[90vh] flex flex-col overflow-hidden">
        <DialogHeader>
          <DialogTitle>Ajouter une nouvelle image</DialogTitle>
          <DialogDescription>
            Téléchargez une image et ajoutez des informations pour la référencer facilement.
          </DialogDescription>
        </DialogHeader>
        
        <ScrollArea className="flex-grow pr-4 overflow-y-auto" style={{ maxHeight: 'calc(80vh - 200px)' }}>
          <form id="upload-form" onSubmit={handleSubmit} className="space-y-5 py-2">
            {!preview ? (
              <div 
                className="border-2 border-dashed rounded-lg p-12 text-center cursor-pointer hover:bg-muted/50 transition-colors"
                onClick={() => fileInputRef.current?.click()}
              >
                <ImagePlus className="h-10 w-10 mx-auto text-muted-foreground" />
                <p className="mt-2 text-sm text-muted-foreground">
                  Cliquez ou glissez-déposez une image
                </p>
                <input
                  ref={fileInputRef}
                  type="file"
                  onChange={handleFileChange}
                  accept="image/*"
                  className="hidden"
                />
              </div>
            ) : (
              <div className="relative">
                <img 
                  src={preview} 
                  alt="Aperçu" 
                  className="w-full rounded-lg max-h-[300px] object-contain bg-muted/30"
                />
                <Button
                  type="button"
                  size="sm"
                  variant="destructive"
                  className="absolute top-2 right-2"
                  onClick={() => {
                    if (preview) URL.revokeObjectURL(preview);
                    setPreview(null);
                    setFile(null);
                    if (fileInputRef.current) fileInputRef.current.value = '';
                  }}
                >
                  <X className="h-4 w-4" />
                </Button>
                
                {dimensions && (
                  <div className="mt-2 text-sm text-muted-foreground flex items-center gap-4">
                    <span>Dimensions: {dimensions.width} × {dimensions.height}</span>
                    <span>Orientation: {orientation}</span>
                  </div>
                )}
              </div>
            )}
            
            <div className="grid gap-4">
              <div>
                <Label htmlFor="project">Projet</Label>
                <Select
                  value={selectedProject}
                  onValueChange={setSelectedProject}
                  disabled={isLoadingProjects}
                >
                  <SelectTrigger id="project" className="w-full">
                    <SelectValue placeholder={isLoadingProjects ? "Chargement des projets..." : "Sélectionner un projet"} />
                  </SelectTrigger>
                  <SelectContent>
                    {projects.length > 0 ? (
                      projects.map((project) => (
                        <SelectItem key={project.id} value={project.id}>
                          {project.nom_projet}
                        </SelectItem>
                      ))
                    ) : (
                      <SelectItem value="no-projects" disabled>
                        Aucun projet disponible
                      </SelectItem>
                    )}
                  </SelectContent>
                </Select>
              </div>
              
              <div>
                <Label htmlFor="title">Titre</Label>
                <Input
                  id="title"
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  required
                />
              </div>
              
              <div>
                <Label htmlFor="description">Description (optionnelle)</Label>
                <Textarea
                  id="description"
                  value={description}
                  onChange={(e) => setDescription(e.target.value)}
                  rows={3}
                />
              </div>
              
              <div>
                <div className="flex items-center justify-between">
                  <Label className="flex items-center">
                    <Tag className="h-4 w-4 mr-2" />
                    Tags sélectionnés
                  </Label>
                </div>
                
                <div className="mt-2 flex flex-wrap gap-2 min-h-10">
                  {tags.length > 0 ? (
                    tags.map((tag, index) => (
                      <Badge 
                        key={index} 
                        variant="secondary" 
                        className="cursor-pointer"
                        onClick={() => toggleTag(tag)}
                      >
                        {tag} <X className="ml-1 h-3 w-3" />
                      </Badge>
                    ))
                  ) : (
                    <p className="text-sm text-muted-foreground">Aucun tag sélectionné</p>
                  )}
                </div>
              </div>

              <div>
                <Label htmlFor="newTag">Ajouter un tag manuellement</Label>
                <div className="flex items-center gap-2 mt-1">
                  <Input
                    id="newTag"
                    ref={tagInputRef}
                    value={newTag}
                    onChange={(e) => setNewTag(e.target.value)}
                    onKeyDown={handleTagInputKeyDown}
                    placeholder="Saisissez un tag et appuyez sur Entrée"
                    maxLength={30}
                  />
                  <Button
                    type="button"
                    size="sm"
                    onClick={addNewTag}
                    disabled={!newTag.trim()}
                  >
                    <PlusCircle className="h-4 w-4 mr-1" />
                    Ajouter
                  </Button>
                </div>
                <p className="text-xs text-muted-foreground mt-1">
                  Appuyez sur Entrée ou cliquez sur Ajouter pour valider un tag
                </p>
              </div>
              
              <div className="flex items-center gap-2">
                <input
                  id="auto-analyze"
                  type="checkbox"
                  checked={autoAnalyzeEnabled} 
                  onChange={toggleAutoAnalyze}
                  className="h-4 w-4 rounded border-gray-300 text-primary focus:ring-primary"
                />
                <Label htmlFor="auto-analyze" className="text-sm font-normal">
                  Analyser automatiquement l'image avec IA
                </Label>
              </div>

              {analyzing ? (
                <div className="flex items-center justify-center py-4">
                  <Loader2 className="h-5 w-5 mr-2 animate-spin" />
                  <span>Analyse de l'image en cours...</span>
                </div>
              ) : (
                <div>
                  <div className="flex items-center justify-between">
                    <Label className="flex items-center">
                      <Tag className="h-4 w-4 mr-2" /> 
                      Tags suggérés par IA
                    </Label>
                    {file && !analyzing && (
                      <Button 
                        type="button" 
                        variant="outline" 
                        size="sm" 
                        onClick={handleManualAnalyze}
                      >
                        Analyser l'image
                      </Button>
                    )}
                    {tagError && <p className="text-xs text-destructive">{tagError}</p>}
                  </div>
                  
                  <div className="mt-2 p-3 border rounded-lg">
                    <div className="flex flex-wrap gap-2 min-h-16">
                      {suggestedTags.length > 0 ? (
                        suggestedTags.map((tag, index) => (
                          <Badge 
                            key={index} 
                            variant={tags.includes(tag) ? "default" : "outline"} 
                            className="cursor-pointer hover:shadow-sm transition-all"
                            onClick={() => toggleTag(tag)}
                          >
                            {tag}
                          </Badge>
                        ))
                      ) : preview ? (
                        <p className="text-sm text-muted-foreground">
                          {autoAnalyzeEnabled ? "Analyse en attente..." : "Aucun tag suggéré disponible"}
                        </p>
                      ) : (
                        <p className="text-sm text-muted-foreground">
                          Téléchargez une image pour obtenir des suggestions de tags
                        </p>
                      )}
                    </div>
                  </div>
                </div>
              )}
            </div>
          </form>
        </ScrollArea>
        
        <DialogFooter className="mt-6 pt-4 border-t">
          <Button 
            type="button" 
            variant="outline" 
            onClick={onClose}
            disabled={uploading}
          >
            Annuler
          </Button>
          <Button 
            type="submit"
            form="upload-form"
            disabled={!file || !selectedProject || uploading}
          >
            {uploading ? (
              <>
                <Loader2 className="mr-2 h-4 w-4 animate-spin" />
                Envoi en cours...
              </>
            ) : (
              <>
                <Upload className="mr-2 h-4 w-4" />
                Télécharger
              </>
            )}
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
