import {
  Navigate,
  Route,
  Routes,
} from "react-router";

import ProtectedRoute from "./components/ProtectedRoute";
import CreateTicketPage from "./pages/CreateTicketPage";
import DashboardPage from "./pages/DashboardPage";
import ForgotPasswordPage from "./pages/ForgotPasswordPage";
import LoginPage from "./pages/LoginPage";
import RegisterPage from "./pages/RegisterPage";
import ResetPasswordPage from "./pages/ResetPasswordPage";
import TicketDetailsPage from "./pages/TicketDetailsPage";
import TicketsPage from "./pages/TicketsPage";
import VerifyOtpPage from "./pages/VerifyOtpPage";
import ProfilePage from "./pages/ProfilePage";
import UsersPage from "./pages/UsersPage";
import UserDetailsPage from "./pages/UserDetailsPage";

function App() {
  return (
    <Routes>
      <Route
        path="/"
        element={
          <Navigate to="/login" replace />
        }
      />

      <Route
        path="/login"
        element={<LoginPage />}
      />

      <Route
        path="/register"
        element={<RegisterPage />}
      />

      <Route
        path="/verify-otp"
        element={<VerifyOtpPage />}
      />

      <Route
        path="/forgot-password"
        element={<ForgotPasswordPage />}
      />

      <Route
        path="/reset-password"
        element={<ResetPasswordPage />}
      />

      <Route
        path="/dashboard"
        element={
          <ProtectedRoute
            allowedRoles={["Admin", "Manager"]}
          >
            <DashboardPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/tickets"
        element={
          <ProtectedRoute
            allowedRoles={[
              "Admin",
              "Manager",
              "SupportAgent",
              "User",
            ]}
          >
            <TicketsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/tickets/create"
        element={
          <ProtectedRoute
            allowedRoles={["Admin", "User"]}
          >
            <CreateTicketPage />
          </ProtectedRoute>
        }
      />

        <Route
          path="/tickets/:ticketId"
          element={
            <ProtectedRoute
              allowedRoles={[
                "Admin",
                "Manager",
                "SupportAgent",
                "User",
              ]}
            >
              <TicketDetailsPage />
            </ProtectedRoute>
          }
        />

        <Route
            path="/profile"
            element={
                <ProtectedRoute
                    allowedRoles={[
                        "Admin",
                        "Manager",
                        "SupportAgent",
                        "User",
                    ]}
                >
                    <ProfilePage />
                </ProtectedRoute>
            }
        />

        <Route
          path="/users"
          element={
              <ProtectedRoute
                  allowedRoles={["Admin"]}
              >
                  <UsersPage />
              </ProtectedRoute>
          }
          />
                  <Route
            path="/users/:id"
            element={
                <ProtectedRoute allowedRoles={["Admin"]}>
                    <UserDetailsPage />
                </ProtectedRoute>
            }
        />
          <Route
            path="*"
            element={
              <Navigate to="/login" replace />
            }
          />
    </Routes>
  );
}

export default App;