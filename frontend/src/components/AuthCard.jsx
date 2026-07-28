import { NavLink } from "react-router";

function AuthCard({ title, subtitle, icon, children }) {
  return (
    <div className="auth-card">
      <div className="auth-tabs">
        <NavLink
          to="/login"
          className={({ isActive }) =>
            isActive ? "auth-tab active" : "auth-tab"
          }
        >
          Sign in
        </NavLink>

        <NavLink
          to="/register"
          className={({ isActive }) =>
            isActive ? "auth-tab active" : "auth-tab"
          }
        >
          Register
        </NavLink>
      </div>

      <div className="auth-heading">
        <div className="auth-heading-icon">{icon}</div>

        <div>
          <h2>{title}</h2>
          <p>{subtitle}</p>
        </div>
      </div>

      <div className="auth-divider" />

      {children}
    </div>
  );
}

export default AuthCard;