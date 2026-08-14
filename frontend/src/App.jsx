import {
  Navigate,
  Route,
  Routes,
} from "react-router";

import ProtectedRoute from "./components/ProtectedRoute";
import AgentRequestsPage from "./pages/AgentRequestsPage";
import AssistantPage from "./pages/AssistantPage";
import CreateTicketPage from "./pages/CreateTicketPage";
import DashboardPage from "./pages/DashboardPage";
import ForgotPasswordPage from "./pages/ForgotPasswordPage";
import LatestUpdatesPage from "./pages/LatestUpdatesPage";
import LoginPage from "./pages/LoginPage";
import ProfilePage from "./pages/ProfilePage";
import RegisterPage from "./pages/RegisterPage";
import ResetPasswordPage from "./pages/ResetPasswordPage";
import TicketDetailsPage from "./pages/TicketDetailsPage";
import TicketsPage from "./pages/TicketsPage";
import UserDetailsPage from "./pages/UserDetailsPage";
import UsersPage from "./pages/UsersPage";
import VerifyOtpPage from "./pages/VerifyOtpPage";

const ALL_ROLES = [
  "Admin",
  "Manager",
  "SupportAgent",
  "User",
];

// Only Admin and Manager can access
// Dashboard and Latest Updates.
const MANAGEMENT_ROLES = [
  "Admin",
  "Manager",
];

// Admin, Manager and SupportAgent
// can access the Support Assistant.
const ASSISTANT_ROLES = [
  "Admin",
  "Manager",
  "SupportAgent",
];

function App() {
  return (
    <Routes>
      <Route
        path="/"
        element={
          <Navigate
            to="/login"
            replace
          />
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
            allowedRoles={MANAGEMENT_ROLES}
          >
            <DashboardPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/activity"
        element={
          <ProtectedRoute
            allowedRoles={MANAGEMENT_ROLES}
          >
            <LatestUpdatesPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/tickets"
        element={
          <ProtectedRoute
            allowedRoles={ALL_ROLES}
          >
            <TicketsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/assistant"
        element={
          <ProtectedRoute
            allowedRoles={ASSISTANT_ROLES}
          >
            <AssistantPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/agent-requests"
        element={
          <ProtectedRoute
            allowedRoles={["Admin"]}
          >
            <AgentRequestsPage />
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
            allowedRoles={ALL_ROLES}
          >
            <TicketDetailsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="/profile"
        element={
          <ProtectedRoute
            allowedRoles={ALL_ROLES}
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
          <ProtectedRoute
            allowedRoles={["Admin"]}
          >
            <UserDetailsPage />
          </ProtectedRoute>
        }
      />

      <Route
        path="*"
        element={
          <Navigate
            to="/login"
            replace
          />
        }
      />
    </Routes>
  );
}

export default App;