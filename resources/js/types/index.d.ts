export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    platform_role: string;
    status: string;
}

export interface Abilities {
    isSuperAdmin: boolean;
    canManageClientContent: boolean;
    canViewClientManagement: boolean;
    canManageUsers: boolean;
    canViewUsers: boolean;
    canViewAccessPeriods: boolean;
    canManageAccessPeriods: boolean;
    canViewOperationalLogs: boolean;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
        abilities: Abilities;
    };
    flash: {
        success?: string | null;
        warning?: string | null;
        error?: string | null;
    };
};
