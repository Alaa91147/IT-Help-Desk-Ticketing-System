import { NavLink, useNavigate } from "react-router";
import { useAuth } from "../../context/AuthContext";

const REPORT_ROLES = [
  "Admin",
  "Manager",
  "SupportAgent",
];

function Sidebar() {
  const navigate = useNavigate();
  const { logout, user } = useAuth();

  const role =
    user?.role?.roleName || user?.roleName || user?.role || "";
  const canViewReports = REPORT_ROLES.includes(role);

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <aside className="sidebar">
      <div className="sidebar-brand">
        <span>HD</span>
        <div>
          <strong>HelpDesk</strong>
          <small>{role}</small>
        </div>
      </div>

      <nav>
        {canViewReports && (
          <NavLink to="/dashboard">Dashboard</NavLink>
        )}

        {canViewReports && (
          <NavLink to="/activity">Latest Updates</NavLink>
        )}

        <NavLink to="/tickets">Tickets</NavLink>

        {role === "User" && (
          <NavLink to="/tickets/create">Create Ticket</NavLink>
        )}

        {role === "Admin" && (
          <NavLink to="/users">Users</NavLink>
        )}

        <NavLink to="/profile">Profile</NavLink>

        <button
          type="button"
          className="sidebar-logout"
          onClick={handleLogout}
        >
          Log out
        </button>
      </nav>
    </aside>
  );
}

export default Sidebar;