import { NavLink, useNavigate } from "react-router";
import { useAuth } from "../../context/AuthContext";

function Sidebar() {
  const navigate = useNavigate();
  const { logout } = useAuth();

  async function handleLogout() {
    await logout();
    navigate("/login", { replace: true });
  }

  return (
    <aside className="sidebar">
      <h2>HelpDesk</h2>

      <nav>
        <NavLink to="/dashboard">Dashboard</NavLink>
        <NavLink to="/tickets">Tickets</NavLink>
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