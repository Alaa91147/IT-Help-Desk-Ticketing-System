import { useState } from "react";
import { Link, useNavigate } from "react-router";

import { loginUser } from "../api/authApi";
import AuthCard from "../components/AuthCard";
import AuthLayout from "../components/AuthLayout";
import FormInput from "../components/FormInput";
import PasswordInput from "../components/PasswordInput";
import { useAuth } from "../context/AuthContext";
import { validateLoginForm } from "../utils/validation";

function LoginPage() {
  const navigate = useNavigate();
  const { login } = useAuth();

  const [formData, setFormData] = useState({
    email: "",
    password: "",
    rememberMe: false,
  });

  const [errors, setErrors] = useState({});
  const [serverMessage, setServerMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event) {
    const { name, value, type, checked } = event.target;

    setFormData((current) => ({
      ...current,
      [name]: type === "checkbox" ? checked : value,
    }));

    setErrors((current) => ({
      ...current,
      [name]: "",
    }));

    setServerMessage("");
  }

  async function handleSubmit(event) {
    event.preventDefault();

    const validationErrors = validateLoginForm(formData);

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    try {
      setIsSubmitting(true);
      setServerMessage("");

      const response = await loginUser({
        email: formData.email.trim(),
        password: formData.password,
      });

      const token = response?.data?.token;
      const user = response?.data?.user;
      console.log("Logged in user:", user);
      if (!token || !user) {
        throw new Error("The server returned an invalid login response.");
      }

      login({
        user,
        token,
        remember: formData.rememberMe,
      });

      const userRole =
        user?.role?.roleName ||
        user?.roleName ||
        user?.role;

      if (userRole === "Admin" || userRole === "Manager") {
        navigate("/dashboard", { replace: true });
      } else if (userRole === "SupportAgent" || userRole === "User") {
        navigate("/tickets", { replace: true });
      } else {
        navigate("/login", { replace: true });
      }
    } catch (error) {
      const backendErrors = error?.data?.errors;

      if (backendErrors) {
        setErrors({
          email: backendErrors.email?.[0] || "",
          password: backendErrors.password?.[0] || "",
        });
      }

      setServerMessage(
        error.message || "Login failed. Please check your information."
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AuthLayout>
      <AuthCard
        icon="♧"
        title="Welcome back"
        subtitle="Sign in to your support workspace"
      >
        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          {serverMessage && (
            <div className="form-alert error-alert">{serverMessage}</div>
          )}

          <FormInput
            id="email"
            label="Email address"
            type="email"
            value={formData.email}
            onChange={handleChange}
            placeholder="name@company.com"
            autoComplete="email"
            icon="✉"
            error={errors.email}
            required
          />

          <PasswordInput
            id="password"
            label="Password"
            value={formData.password}
            onChange={handleChange}
            placeholder="Enter your password"
            autoComplete="current-password"
            error={errors.password}
            required
          />

          <div className="form-options">
            <label className="checkbox-label">
              <input
                type="checkbox"
                name="rememberMe"
                checked={formData.rememberMe}
                onChange={handleChange}
              />
              <span>Remember me</span>
            </label>

            <Link to="/forgot-password" className="forgot-link">
              Forgot password?
            </Link>
          </div>

          <button
            type="submit"
            className="primary-button"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Signing in..." : "Sign in"}
            {!isSubmitting && <span>→</span>}
          </button>

          <p className="auth-switch-text">
            Don&apos;t have an account?{" "}
            <Link to="/register">Create account</Link>
          </p>
        </form>
      </AuthCard>
    </AuthLayout>
  );
}

export default LoginPage;