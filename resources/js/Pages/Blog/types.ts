export type BlogPost = {
    id: number;
    title: string;
    slug: string;
    content: string;
    excerpt: string;
    contentType: "resource" | "ensemble";
    contentTypeLabel: string;
    category: string | null;
    categoryLabel: string | null;
    clientId: number | null;
    clientName: string | null;
    featuredImageUrl: string | null;
    featuredImageObjectKey: string | null;
    isPublished: boolean;
    publishedAt: string | null;
    createdAt: string;
    updatedAt: string;
};

export type BlogClientOption = {
    id: number;
    name: string;
};

export type BlogImageOption = {
    id: number;
    title: string;
    clientName: string | null;
    projectName: string | null;
    thumbUrl: string | null;
    objectKey: string | null;
};

export type BlogFormData = {
    title: string;
    content: string;
    client_id: number | null;
    content_type: "resource" | "ensemble";
    category: "actualites" | "projets" | "conseils" | null;
    featured_image_id: number | null;
    remove_featured_image: boolean;
    is_published: boolean;
};
