import { useState } from "react";
import { Link } from "react-router";
import AuthLayout from "../components/AuthLayout";
import AuthCard from "../components/AuthCard";
import FormInput from "../components/FormInput";
import { forgotPassword } from "../api/authApi";
import { isValidEmail } from "../utils/validation";

function ForgotPasswordPage() {
  const [email, setEmail] = useState("");
  const [emailError, setEmailError] = useState("");
  const [serverMessage, setServerMessage] = useState("");
  const [serverError, setServerError] = useState("");
  const [isLoading, setIsLoading] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();

    setEmailError("");
    setServerMessage("");
    setServerError("");

    const trimmedEmail = email.trim();

    if (!trimmedEmail) {
      setEmailError("Email address is required.");
      return;
    }

    if (!isValidEmail(trimmedEmail)) {
      setEmailError("Please enter a valid email address.");
      return;
    }

    try {
      setIsLoading(true);

      const response = await forgotPassword(trimmedEmail);

      setServerMessage(
        response.message ||
          "A password reset code has been sent to your email address."
      );
    } catch (error) {
      setServerError(
        error.message ||
          "We could not process your request. Please try again."
      );
    } finally {
      setIsLoading(false);
    }
  };

  return (
    <AuthLayout>
      <AuthCard
        icon="🔐"
        title="Forgot your password?"
        subtitle="Enter your email address and we’ll send you a verification code."
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
            value={email}
            onChange={(event) => {
              setEmail(event.target.value);

              if (emailError) {
                setEmailError("");
              }

              if (serverError) {
                setServerError("");
              }
            }}
            error={emailError}
            icon="✉"
            autoComplete="email"
            required
          />

          <button
            className="primary-button"
            type="submit"
            disabled={isLoading}
          >
            {isLoading ? "Sending..." : "Send verification code"}
          </button>

          <div className="auth-back-link">
            <Link to="/login">← Back to login</Link>
          </div>
        </form>
      </AuthCard>
    </AuthLayout>
  );
}

export default ForgotPasswordPage;