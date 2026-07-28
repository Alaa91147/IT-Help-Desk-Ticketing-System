import { useAuth } from "../../context/AuthContext";

function Topbar() {
  const { user } = useAuth();

  return (
    <header className="topbar">
      <div>
        <h2>Dashboard</h2>
        <p>Welcome {user?.first_name ?? user?.firstName ?? user?.name}</p>
      </div>
    </header>
  );
}

export default Topbar;