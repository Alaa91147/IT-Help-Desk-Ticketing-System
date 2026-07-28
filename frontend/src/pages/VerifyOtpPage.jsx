import { useState } from "react";
import { Link, useLocation, useNavigate } from "react-router";

import { resendOtp, verifyOtp } from "../api/authApi";
import AuthCard from "../components/AuthCard";
import AuthLayout from "../components/AuthLayout";
import FormInput from "../components/FormInput";
import { validateOtp } from "../utils/validation";

function VerifyOtpPage() {
  const navigate = useNavigate();
  const location = useLocation();

  const [email, setEmail] = useState(location.state?.email || "");
  const [otp, setOtp] = useState("");
  const [errors, setErrors] = useState({});
  const [serverMessage, setServerMessage] = useState(
    location.state?.message || ""
  );
  const [messageType, setMessageType] = useState(
    location.state?.message ? "success" : ""
  );
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [isResending, setIsResending] = useState(false);

  function handleEmailChange(event) {
    setEmail(event.target.value);
    setErrors((current) => ({
      ...current,
      email: "",
    }));
    setServerMessage("");
  }

  function handleOtpChange(event) {
    const digitsOnly = event.target.value.replace(/\D/g, "").slice(0, 6);

    setOtp(digitsOnly);
    setErrors((current) => ({
      ...current,
      otp: "",
    }));
    setServerMessage("");
  }

  async function handleSubmit(event) {
    event.preventDefault();

    const nextErrors = {};
    const otpError = validateOtp(otp);

    if (!email.trim()) {
      nextErrors.email = "Email address is required.";
    }

    if (otpError) {
      nextErrors.otp = otpError;
    }

    if (Object.keys(nextErrors).length > 0) {
      setErrors(nextErrors);
      return;
    }

    try {
      setIsSubmitting(true);
      setServerMessage("");

      const response = await verifyOtp(email.trim(), otp);

      navigate("/login", {
        replace: true,
        state: {
          message:
            response?.message ||
            "Email verified successfully. You can now sign in.",
        },
      });
    } catch (error) {
      const backendErrors = error?.data?.errors;

      if (backendErrors) {
        setErrors({
          email: backendErrors.email?.[0] || "",
          otp: backendErrors.otp?.[0] || "",
        });
      }

      setMessageType("error");
      setServerMessage(
        error.message || "Verification failed. Please try again."
      );
    } finally {
      setIsSubmitting(false);
    }
  }

  async function handleResend() {
    if (!email.trim()) {
      setErrors({
        email: "Enter your email address before requesting a new code.",
      });
      return;
    }

    try {
      setIsResending(true);
      setServerMessage("");

      const response = await resendOtp(email.trim());

      setMessageType("success");
      setServerMessage(
        response?.message || "A new verification code was sent."
      );
    } catch (error) {
      setMessageType("error");
      setServerMessage(
        error.message || "Could not resend the verification code."
      );
    } finally {
      setIsResending(false);
    }
  }

  return (
    <AuthLayout>
      <AuthCard
        icon="✓"
        title="Verify your email"
        subtitle="Enter the 6-digit code sent to your email"
      >
        <form className="auth-form" onSubmit={handleSubmit} noValidate>
          {serverMessage && (
            <div
              className={`form-alert ${
                messageType === "success" ? "success-alert" : "error-alert"
              }`}
            >
              {serverMessage}
            </div>
          )}

          <FormInput
            id="email"
            label="Email address"
            type="email"
            value={email}
            onChange={handleEmailChange}
            placeholder="name@company.com"
            autoComplete="email"
            icon="✉"
            error={errors.email}
            required
          />

          <FormInput
            id="otp"
            label="Verification code"
            type="text"
            value={otp}
            onChange={handleOtpChange}
            placeholder="123456"
            autoComplete="one-time-code"
            icon="#"
            error={errors.otp}
            required
          />

          <button
            type="submit"
            className="primary-button"
            disabled={isSubmitting}
          >
            {isSubmitting ? "Verifying..." : "Verify email"}
            {!isSubmitting && <span>→</span>}
          </button>

          <button
            type="button"
            className="secondary-button"
            onClick={handleResend}
            disabled={isResending}
          >
            {isResending ? "Sending..." : "Resend verification code"}
          </button>

          <p className="auth-switch-text">
            Already verified? <Link to="/login">Return to sign in</Link>
          </p>
        </form>
      </AuthCard>
    </AuthLayout>
  );
}

export default VerifyOtpPage;