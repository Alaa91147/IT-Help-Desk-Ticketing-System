import { useState } from "react";
import { Link, useNavigate } from "react-router";
import AuthLayout from "../components/AuthLayout";
import AuthCard from "../components/AuthCard";
import FormInput from "../components/FormInput";
import PasswordInput from "../components/PasswordInput";
import { resetPassword } from "../api/authApi";
import { isValidEmail, validateOtp } from "../utils/validation";

function ResetPasswordPage() {
  const navigate = useNavigate();

  const [formData, setFormData] = useState({
    email: "",
    token: "",
    password: "",
    passwordConfirmation: "",
  });

  const [errors, setErrors] = useState({});
  const [serverError, setServerError] = useState("");
  const [serverMessage, setServerMessage] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleChange = (event) => {
    const { name, value } = event.target;

    setFormData((current) => ({
      ...current,
      [name]: value,
    }));

    if (errors[name]) {
      setErrors((current) => ({
        ...current,
        [name]: "",
      }));
    }

    if (serverError) {
      setServerError("");
    }
  };

  const validateForm = () => {
    const nextErrors = {};

    if (!formData.email.trim()) {
      nextErrors.email = "Email address is required.";
    } else if (!isValidEmail(formData.email.trim())) {
      nextErrors.email = "Please enter a valid email address.";
    }

    const tokenError = validateOtp(formData.token);

    if (tokenError) {
      nextErrors.token = tokenError;
    }

    if (!formData.password) {
      nextErrors.password = "New password is required.";
    } else if (formData.password.length < 8) {
      nextErrors.password = "Password must contain at least 8 characters.";
    }

    if (!formData.passwordConfirmation) {
      nextErrors.passwordConfirmation =
        "Please confirm your new password.";
    } else if (
      formData.passwordConfirmation !== formData.password
    ) {
      nextErrors.passwordConfirmation = "Passwords do not match.";
    }

    return nextErrors;
  };

  const handleSubmit = async (event) => {
    event.preventDefault();

    setServerError("");
    setServerMessage("");

    const nextErrors = validateForm();

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    try {
      setIsLoading(true);

      const response = await resetPassword({
        email: formData.email.trim(),
        token: formData.token.trim(),
        password: formData.password,
        confirmPassword: formData.passwordConfirmation,
      });

      setServerMessage(
        response.message || "Your password has been reset successfully."
      );

      setTimeout(() => {
        navigate("/login");
      }, 1500);
    } catch (error) {
      setServerError(
        error.message ||
          "We could not reset your password. Please check the code and try again."
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout>
      <AuthCard
        icon="🔑"
        title="Reset your password"
        subtitle="Enter the verification code and choose a new password."
        showTabs={false}
      >
        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          {serverMessage && (
            <div className="alert alert-success" role="status">
              {serverMessage}
            </div>
          )}

          {serverError && (
            <div className="alert alert-error" role="alert">
              {serverError}
            </div>
          )}

          <FormInput
            label="Email address"
            type="email"
            name="email"
            placeholder="Enter your email address"
            value={formData.email}
            onChange={handleChange}
            error={errors.email}
            icon="✉"
            autoComplete="email"
            required
          />

          <FormInput
            label="Verification code"
            type="text"
            name="token"
            placeholder="Enter the verification code"
            value={formData.token}
            onChange={(event) => {
              const numericValue = event.target.value
                .replace(/\D/g, "")
                .slice(0, 6);

              handleChange({
                target: {
                  name: "token",
                  value: numericValue,
                },
              });
            }}
            error={errors.token}
            icon="#"
            inputMode="numeric"
            required
          />

          <PasswordInput
            label="New password"
            name="password"
            placeholder="Enter your new password"
            value={formData.password}
            onChange={handleChange}
            error={errors.password}
            autoComplete="new-password"
            required
          />

          <PasswordInput
            label="Confirm new password"
            name="passwordConfirmation"
            placeholder="Confirm your new password"
            value={formData.passwordConfirmation}
            onChange={handleChange}
            error={errors.passwordConfirmation}
            autoComplete="new-password"
            required
          />

          <button
            className="primary-button"
            type="submit"
            disabled={isLoading}
          >
            {isLoading ? "Resetting..." : "Reset password"}
          </button>

          <div className="auth-back-link">
            <Link to="/login">← Back to login</Link>
          </div>
        </form>
      </AuthCard>
    </AuthLayout>
  );
}

export default ResetPasswordPage;