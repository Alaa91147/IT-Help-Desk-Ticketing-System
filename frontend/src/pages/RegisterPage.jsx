import { useState } from "react";
import { Link, useNavigate } from "react-router";

import { registerUser } from "../api/authApi";
import AuthCard from "../components/AuthCard";
import AuthLayout from "../components/AuthLayout";
import FormInput from "../components/FormInput";
import PasswordInput from "../components/PasswordInput";
import { validateRegisterForm } from "../utils/validation";

function RegisterPage() {
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    firstName: "",
    lastName: "",
    email: "",
    phoneNumber: "",
    password: "",
    confirmPassword: "",
  });

  const [errors, setErrors] = useState({});
  const [serverMessage, setServerMessage] = useState("");
  const [isSubmitting, setIsSubmitting] = useState(false);

  function handleChange(event) {
    const { name, value } = event.target;

    setFormData((current) => ({
      ...current,
      [name]: value,
    }));

    setErrors((current) => ({
      ...current,
      [name]: "",
    }));

    setServerMessage("");
  }

  async function handleSubmit(event) {
    event.preventDefault();

    const validationErrors = validateRegisterForm(formData);

    if (Object.keys(validationErrors).length > 0) {
      setErrors(validationErrors);
      return;
    }

    try {
      setIsSubmitting(true);
      setServerMessage("");

      const response = await registerUser({
        firstName: formData.firstName.trim(),
        lastName: formData.lastName.trim(),
        email: formData.email.trim(),
        phoneNumber: formData.phoneNumber.trim(),
        password: formData.password,
        confirmPassword: formData.confirmPassword,
      });

      const requiresVerification =
        response?.data?.requiresEmailVerification ?? true;

      if (requiresVerification) {
        navigate("/verify-otp", {
          replace: true,
          state: {
            email: formData.email.trim(),
            message:
              response?.message ||
              "Registration successful. Check your verification code.",
          },
        });

        return;
      }

      navigate("/login", {
        replace: true,
        state: {
          message:
            response?.message ||
            "Registration successful. You can now sign in.",
        },
      });
    } catch (error) {
      const backendErrors = error?.data?.errors;

      if (backendErrors) {
        setErrors({
          firstName: backendErrors.firstName?.[0] || "",
          lastName: backendErrors.lastName?.[0] || "",
          email: backendErrors.email?.[0] || "",
          phoneNumber: backendErrors.phoneNumber?.[0] || "",
          password: backendErrors.password?.[0] || "",
          confirmPassword:
            backendErrors.password_confirmation?.[0] || "",
        });
      }

      setServerMessage(
        error.message || "Registration failed. Please try again."
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  return (
    <AuthLayout>
      <AuthCard
        icon="＋"
        title="Create your account"
        subtitle="Join your team’s support workspace"
      >
        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          {serverMessage && (
            <div className="form-alert error-alert">{serverMessage}</div>
          )}

          <div className="form-row">
            <FormInput
              id="firstName"
              label="First name"
              value={formData.firstName}
              onChange={handleChange}
              placeholder="Fatima"
              autoComplete="given-name"
              icon="◯"
              error={errors.firstName}
              required
            />

            <FormInput
              id="lastName"
              label="Last name"
              value={formData.lastName}
              onChange={handleChange}
              placeholder="Rahal"
              autoComplete="family-name"
              icon="◯"
              error={errors.lastName}
              required
            />
          </div>

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

          <FormInput
            id="phoneNumber"
            label="Phone number"
            type="tel"
            value={formData.phoneNumber}
            onChange={handleChange}
            placeholder="+961 70 123 456"
            autoComplete="tel"
            icon="☎"
            error={errors.phoneNumber}
          />

          <PasswordInput
            id="password"
            label="Password"
            value={formData.password}
            onChange={handleChange}
            placeholder="At least 8 characters"
            autoComplete="new-password"
            error={errors.password}
            required
          />

          <PasswordInput
            id="confirmPassword"
            label="Confirm password"
            value={formData.confirmPassword}
            onChange={handleChange}
            placeholder="Enter your password again"
            autoComplete="new-password"
            error={errors.confirmPassword}
            required
          />

          <button
            type="submit"
            className="primary-button"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Creating account..." : "Create account"}
            {!isSubmitting && <span>→</span>}
          </button>

          <p className="auth-switch-text">
            Already have an account? <Link to="/login">Sign in</Link>
          </p>
        </form>
      </AuthCard>
    </AuthLayout>
  );
}

export default RegisterPage;