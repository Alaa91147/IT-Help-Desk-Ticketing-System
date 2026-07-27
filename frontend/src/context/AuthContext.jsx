import {
  createContext,
  useContext,
  useEffect,
  useMemo,
  useState,
} from "react";
import { getCurrentUser, logoutUser } from "../api/authApi";
import {
  clearAuthData,
  getAuthToken,
  getStoredUser,
  saveAuthData,
} from "../utils/authStorage";

const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(() => getStoredUser());
  const [token, setToken] = useState(() => getAuthToken());
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    const restoreAuthentication = async () => {
      const storedToken = getAuthToken();

      if (!storedToken) {
        setUser(null);
        setToken(null);
        setIsLoading(false);
        return;
      }

      try {
        const response = await getCurrentUser();

        const currentUser = response.data?.user ?? response.user ?? response.data;

        setUser(currentUser);
        setToken(storedToken);
      } catch {
        clearAuthData();
        setUser(null);
        setToken(null);
      } finally {
        setIsLoading(false);
      }
    };

    restoreAuthentication();
  }, []);

  const login = ({
    user: authenticatedUser,
    token: authenticationToken,
    remember = false,
  }) => {
    saveAuthData(
      authenticationToken,
      authenticatedUser,
      remember
    );

    setUser(authenticatedUser);
    setToken(authenticationToken);
  };

  const logout = async () => {
    try {
      if (getAuthToken()) {
        await logoutUser();
      }
    } catch (error) {
      console.error("Logout request failed:", error);
    } finally {
      clearAuthData();
      setUser(null);
      setToken(null);
    }
  };

  const value = useMemo(
    () => ({
      user,
      token,
      isLoading,
      isAuthenticated: Boolean(token && user),
      login,
      logout,
    }),
    [user, token, isLoading]
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}

export function useAuth() {
  const context = useContext(AuthContext);

  if (!context) {
    throw new Error("useAuth must be used inside AuthProvider.");
  }

  return context;
}