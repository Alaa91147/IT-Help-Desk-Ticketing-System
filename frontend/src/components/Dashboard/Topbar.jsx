import NotificationBell from "../Notifications/NotificationBell";
import { useAuth } from "../../context/AuthContext";

function Topbar() {
  const { user } = useAuth();

  const name =
    user?.fullName ||
    `${user?.firstName || ""} ${user?.lastName || ""}`.trim() ||
    "User";

  return (
    <header className="topbar">
      <div>
        <h2>Operations dashboard</h2>
        <p>Welcome, {name}</p>
      </div>
      <NotificationBell />
    </header>
  );
}

export default Topbar;