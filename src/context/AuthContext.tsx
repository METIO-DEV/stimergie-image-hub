
import { createContext, useContext, useEffect, useState, ReactNode } from 'react';
import { Session, User } from '@supabase/supabase-js';
import { clearSupabaseAuthStorage, supabase } from '@/integrations/supabase/client';
import { useToast } from '@/hooks/use-toast';

type UserRole = 'admin' | 'admin_client' | 'user' | string;

type AuthContextType = {
  session: Session | null;
  user: User | null;
  userRole: UserRole;
  userClientId: string | null;
  loading: boolean;
  signIn: (email: string, password: string) => Promise<void>;
  signUp: (email: string, password: string, firstName: string, lastName: string) => Promise<void>;
  signOut: () => Promise<void>;
  changePassword: (currentPassword: string, newPassword: string) => Promise<void>;
  isAdmin: () => boolean;
  isAdminClient: () => boolean;
  isRegularUser: () => boolean;
  canAccessClient: (clientId: string | null) => Promise<boolean>;
  canEditClient: (clientId: string | null) => Promise<boolean>;
};

const AuthContext = createContext<AuthContextType | undefined>(undefined);

export function AuthProvider({ children }: { children: ReactNode }) {
  const [session, setSession] = useState<Session | null>(null);
  const [user, setUser] = useState<User | null>(null);
  const [userRole, setUserRole] = useState<UserRole>('user');
  const [loading, setLoading] = useState(true);
  const [userClientId, setUserClientId] = useState<string | null>(null);
  const { toast } = useToast();

  useEffect(() => {
    // Check for valid session on mount
    supabase.auth.getSession().then(({ data: { session }, error }) => {
      console.log("🔐 Initial session check:", session?.user?.id || 'No session');
      
      if (error) {
        console.error("❌ Error getting initial session:", error.message);
        
        // Clear any invalid session data from localStorage
        clearSupabaseAuthStorage();
        
        setSession(null);
        setUser(null);
        setUserRole('user');
        setUserClientId(null);
        setLoading(false);
        return;
      }
      
      setSession(session);
      setUser(session?.user ?? null);
      
      if (session?.user) {
        fetchUserRoleViaRPC(session.user.id);
        fetchUserClientId(session.user.id);
      } else {
        setLoading(false);
      }
    });

    const { data: { subscription } } = supabase.auth.onAuthStateChange(
      (event, session) => {
        console.log("🔄 Auth state change:", event, "| User:", session?.user?.id || 'None');
        
        // Handle token refresh errors - Critical for detecting invalid refresh tokens
        if (event === 'TOKEN_REFRESHED') {
          if (!session) {
            console.error("❌ Token refresh failed - no session returned - forcing logout");
            
            // Clear all invalid tokens from storage
            clearSupabaseAuthStorage();
            
            // Reset all auth state
            setSession(null);
            setUser(null);
            setUserRole('user');
            setUserClientId(null);
            setLoading(false);
            
            toast({
              title: "Session expirée",
              description: "Votre session a expiré. Veuillez vous reconnecter.",
              variant: "destructive"
            });
            
            // Force redirect to auth page
            setTimeout(() => {
              window.location.href = '/auth';
            }, 100);
            return;
          }
          console.log("✅ Token refreshed successfully");
        }
        
        // Handle sign out event
        if (event === 'SIGNED_OUT') {
          console.log("👋 User signed out");
          
          // Clear all auth data
          clearSupabaseAuthStorage();
          
          setSession(null);
          setUser(null);
          setUserRole('user');
          setUserClientId(null);
          setLoading(false);
          return;
        }
        
        // Handle sign in success
        if (event === 'SIGNED_IN' && session) {
          console.log("✅ User signed in successfully");
        }
        
        setSession(session);
        setUser(session?.user ?? null);
        
        if (session?.user) {
          // Use setTimeout to avoid blocking the auth state change
          setTimeout(() => {
            fetchUserRoleViaRPC(session.user.id);
            fetchUserClientId(session.user.id);
          }, 0);
        } else {
          setUserRole('user');
          setUserClientId(null);
          setLoading(false);
        }
      }
    );

    return () => subscription.unsubscribe();
  }, [toast]);

  // Periodic session validity check - helps catch invalid refresh tokens
  useEffect(() => {
    const checkSessionValidity = async () => {
      try {
        const { data: { session }, error } = await supabase.auth.getSession();
        
        if (error || !session) {
          console.error("❌ Session invalide détectée lors de la vérification périodique:", error?.message);
          
          // Clear invalid tokens from storage
          clearSupabaseAuthStorage();
          
          // Reset all auth state
          setSession(null);
          setUser(null);
          setUserRole('user');
          setUserClientId(null);
          
          toast({
            title: "Session invalide",
            description: "Votre session n'est plus valide. Veuillez vous reconnecter.",
            variant: "destructive"
          });
          
          // Force redirect to auth page
          setTimeout(() => {
            window.location.href = '/auth';
          }, 100);
        }
      } catch (error) {
        console.error("❌ Erreur lors de la vérification de session:", error);
      }
    };
    
    // Check session validity every 5 minutes
    const interval = setInterval(checkSessionValidity, 5 * 60 * 1000);
    
    return () => clearInterval(interval);
  }, [toast]);

  const fetchUserClientId = async (userId: string) => {
    try {
      const { data, error } = await supabase.rpc('get_user_client_id', {
        user_id: userId
      });
      
      if (error) {
        console.error("Error fetching user client ID:", error);
        return;
      }
      
      setUserClientId(data);
    } catch (error) {
      console.error("Error in fetchUserClientId:", error);
    }
  };

  const fetchUserRoleViaRPC = async (userId: string) => {
    try {
      console.log("👤 Fetching user role for:", userId);
      const { data, error } = await supabase.rpc('get_user_role');
      
      if (error) {
        console.error("❌ Error fetching user role:", error.message);
        
        // Check for authentication/token errors
        const isAuthError = error.message?.includes('Invalid Refresh Token') || 
                           error.message?.includes('JWT expired') ||
                           error.message?.includes('refresh_token_not_found') ||
                           error.code === 'PGRST301';
        
        if (isAuthError) {
          console.error("🔐 Authentication error detected, signing out...");
          
          // Clear storage and sign out
          clearSupabaseAuthStorage();
          
          toast({
            title: "Session invalide",
            description: "Veuillez vous reconnecter",
            variant: "destructive"
          });
          
          await supabase.auth.signOut();
          return;
        }
        
        await fallbackFetchRole(userId);
        return;
      }
      
      console.log("✅ User role from RPC:", data);
      if (data) {
        setUserRole(data);
      } else {
        await fallbackFetchRole(userId);
      }
    } catch (error) {
      console.error("❌ Unexpected error fetching role:", error);
      
      // Check for authentication errors in catch block
      if (error instanceof Error) {
        const isAuthError = error.message?.includes('Invalid Refresh Token') || 
                           error.message?.includes('JWT expired') ||
                           error.message?.includes('refresh_token_not_found');
        
        if (isAuthError) {
          console.error("🔐 Authentication error in catch, signing out...");
          clearSupabaseAuthStorage();
          await supabase.auth.signOut();
          return;
        }
      }
      
      await fallbackFetchRole(userId);
    } finally {
      setLoading(false);
    }
  };

  const fallbackFetchRole = async (userId: string) => {
    try {
      console.log("Using fallback method to fetch role for:", userId);
      
      if (user?.user_metadata?.role) {
        console.log("Found role in user metadata:", user.user_metadata.role);
        setUserRole(user.user_metadata.role);
        return;
      }
      
      try {
        const { data: isAdminResult, error: isAdminError } = await supabase.rpc('is_admin');
        
        if (!isAdminError && isAdminResult === true) {
          setUserRole('admin');
          return;
        }
        
        const { data: isAdminClientResult, error: isAdminClientError } = await supabase.rpc('is_admin_client');
        
        if (!isAdminClientError && isAdminClientResult === true) {
          setUserRole('admin_client');
          return;
        }
        
        setUserRole('user');
      } catch (error) {
        console.error("Error calling role check functions:", error);
        setUserRole('user');
      }
    } catch (error) {
      console.error("Fallback role fetch failed:", error);
      setUserRole('user');
    }
  };

  const signIn = async (email: string, password: string) => {
    try {
      console.log("AuthContext signIn called for:", email);
      
      const trimmedEmail = email.trim();
      const trimmedPassword = password.trim();
      
      // Log the credentials being used (without the password)
      console.log("Attempting login with email:", trimmedEmail);
      
      const { data, error } = await supabase.auth.signInWithPassword({ 
        email: trimmedEmail, 
        password: trimmedPassword
      });
      
      if (error) {
        console.error('Error signing in (AuthContext):', error);
        
        let errorMessage = "Échec de connexion";
        
        if (error.message === "Invalid login credentials") {
          errorMessage = "Email ou mot de passe incorrect";
        } else if (error.message.includes("Email not confirmed")) {
          errorMessage = "Veuillez confirmer votre email avant de vous connecter";
        } else {
          errorMessage = error.message;
        }
        
        toast({
          title: "Échec de connexion",
          description: errorMessage,
          variant: "destructive"
        });
        
        throw error;
      }
      
      console.log("Sign in successful in AuthContext, user data:", data?.user?.id);
      
      toast({
        title: "Connecté avec succès",
        description: "Bienvenue sur votre tableau de bord",
      });
    } catch (error) {
      console.error('Login error details in AuthContext:', error);
      throw error;
    }
  };

  const signUp = async (email: string, password: string, firstName: string, lastName: string) => {
    try {
      const { error } = await supabase.auth.signUp({
        email,
        password,
        options: {
          data: {
            first_name: firstName,
            last_name: lastName,
            role: 'user'
          },
        },
      });

      if (error) {
        console.error('Error signing up:', error);
        toast({
          title: "Échec d'inscription",
          description: error.message,
          variant: "destructive"
        });
        throw error;
      }
      
      toast({
        title: "Compte créé avec succès",
        description: "Veuillez vérifier votre e-mail pour confirmer votre inscription",
      });
    } catch (error) {
      console.error('Signup error:', error);
      throw error;
    }
  };

  const signOut = async () => {
    try {
      const { data: sessionData } = await supabase.auth.getSession();
      
      if (!sessionData.session) {
        console.log('No active session found, cleaning up local state only');
        setUser(null);
        setSession(null);
        setUserRole('user');
        setUserClientId(null);
        
        toast({
          title: "Déconnecté avec succès",
        });
        return;
      }
      
      const { error } = await supabase.auth.signOut();
      
      if (error) {
        console.error('Error signing out:', error);
        toast({
          title: "Échec de déconnexion",
          description: error.message,
          variant: "destructive"
        });
        throw error;
      }
      
      toast({
        title: "Déconnecté avec succès",
      });
    } catch (error) {
      console.error('Logout error:', error);
      
      setUser(null);
      setSession(null);
      setUserRole('user');
      setUserClientId(null);
      
      if (error instanceof Error && !error.message.includes('Auth session missing')) {
        throw error;
      }
    }
  };

  const changePassword = async (currentPassword: string, newPassword: string) => {
    try {
      if (!user) {
        throw new Error("Vous devez être connecté pour changer votre mot de passe");
      }

      const { error: verifyError } = await supabase.auth.signInWithPassword({
        email: user.email as string,
        password: currentPassword,
      });

      if (verifyError) {
        toast({
          title: "Erreur",
          description: "Le mot de passe actuel est incorrect",
          variant: "destructive"
        });
        throw new Error("Le mot de passe actuel est incorrect");
      }

      const { error } = await supabase.auth.updateUser({
        password: newPassword,
      });

      if (error) {
        console.error('Erreur lors du changement de mot de passe:', error);
        toast({
          title: "Échec du changement de mot de passe",
          description: error.message,
          variant: "destructive"
        });
        throw error;
      }

      toast({
        title: "Mot de passe modifié",
        description: "Votre mot de passe a été changé avec succès",
      });
    } catch (error) {
      console.error('Password change error:', error);
      throw error;
    }
  };

  const isAdmin = () => userRole === 'admin';
  const isAdminClient = () => userRole === 'admin_client';
  const isRegularUser = () => userRole === 'user';
  
  const canAccessClient = async (clientId: string | null) => {
    if (isAdmin()) return true;
    
    if (!clientId) return false;
    
    try {
      const { data, error } = await supabase.rpc('check_can_access_client', {
        client_id: clientId
      });
      
      if (error) {
        console.error("Error checking client access:", error);
        return false;
      }
      
      return data === true;
    } catch (error) {
      console.error("Error in canAccessClient:", error);
      return false;
    }
  };

  const canEditClient = async (clientId: string | null) => {
    if (isAdmin()) return true;
    
    if (isAdminClient() && userClientId === clientId) {
      return true;
    }
    
    return false;
  };

  return (
    <AuthContext.Provider value={{ 
      session, 
      user, 
      userRole,
      userClientId,
      loading, 
      signIn, 
      signUp, 
      signOut,
      changePassword,
      isAdmin,
      isAdminClient,
      isRegularUser,
      canAccessClient,
      canEditClient
    }}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);
  
  if (context === undefined) {
    throw new Error('useAuth must be used within an AuthProvider');
  }
  
  return context;
}
