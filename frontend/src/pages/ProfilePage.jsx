import DashboardLayout from "../components/Dashboard/DashboardLayout";
import { useAuth } from "../context/AuthContext";
import "../styles/profile.css";
import { useState } from "react";
import { useNavigate } from "react-router";
import {
  updateProfile,
  verifyEmailChange,
  getCurrentUser,
} from "../api/authApi";

function ProfilePage() {
  const { user, token, updateUser } = useAuth();
  const navigate = useNavigate();

  const role =
    user?.role?.roleName ||
    user?.roleName ||
    user?.role ||
    "Unknown";

  const isAdmin =
    role === "Admin" ||
    role === "Manager";

  const [form, setForm] = useState({
    firstName: user?.firstName || "",
    lastName: user?.lastName || "",
    email: user?.email || "",
    phoneNumber: user?.phoneNumber || "",
    currentPassword: "",
    newPassword: "",
    confirmPassword: "",
  });

  const [showOtp, setShowOtp] = useState(false);
  const [otp, setOtp] = useState("");
  const [saving, setSaving] = useState(false);

  function handleChange(e) {
    setForm({
      ...form,
      [e.target.name]: e.target.value,
    });
  }

  async function refreshCurrentUser() {
    const latestUserResponse = await getCurrentUser(token);

    const latestUser =
      latestUserResponse.data?.user ??
      latestUserResponse.user ??
      latestUserResponse.data;

    if (latestUser) {
      updateUser(latestUser);
    }
  }

  async function handleSubmit(e) {
    e.preventDefault();
    setSaving(true);

    try {
      const response = await updateProfile(form, token);

      if (response.data?.requiresEmailOtp) {
        alert(response.message);
        setShowOtp(true);
        return;
      }

      await refreshCurrentUser();

      alert(
        response.message ||
        "Profile updated successfully!"
      );

      setForm((previousForm) => ({
        ...previousForm,
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
      }));
    } catch (error) {
      console.error(error);

      alert(
        error?.message ||
        "Unable to update profile."
      );
    } finally {
      setSaving(false);
    }
  }

  async function handleOtpVerification() {
    try {
      const response = await verifyEmailChange(
        otp,
        token
      );

      await refreshCurrentUser();

      alert(
        response.message ||
        "Email updated successfully!"
      );

      setShowOtp(false);
      setOtp("");
    } catch (error) {
      console.error(error);

      alert(
        error?.message ||
        "Invalid verification code."
      );
    }
  }

  const profileContent = (
    <div className="profile-container">
      <button
        type="button"
        onClick={() => navigate("/tickets")}
        style={{
          border: "none",
          background: "none",
          cursor: "pointer",
          fontSize: "18px",
          marginBottom: "20px",
          color: "#2563eb",
        }}
      >
        ← Back to Tickets
      </button>

      <h2>My Profile</h2>

      <form
        className="profile-card"
        onSubmit={handleSubmit}
      >
        <div className="profile-field">
          <label>First Name</label>

          <input
            name="firstName"
            value={form.firstName}
            onChange={handleChange}
          />
        </div>

        <div className="profile-field">
          <label>Last Name</label>

          <input
            name="lastName"
            value={form.lastName}
            onChange={handleChange}
          />
        </div>

        <div className="profile-field">
          <label>Phone Number</label>

          <input
            name="phoneNumber"
            value={form.phoneNumber}
            onChange={handleChange}
          />
        </div>

        <div className="profile-field">
          <label>Email</label>

          <input
            name="email"
            value={form.email}
            onChange={handleChange}
          />
        </div>

        <div className="profile-field">
          <label>Role</label>

          <input
            value={role}
            disabled
          />
        </div>

        <div className="profile-field">
          <label>Current Password</label>

          <input
            type="password"
            name="currentPassword"
            value={form.currentPassword}
            onChange={handleChange}
            placeholder="Enter your current password"
          />
        </div>

        <div className="profile-field">
          <label>New Password</label>

          <input
            type="password"
            name="newPassword"
            value={form.newPassword}
            onChange={handleChange}
            placeholder="Leave blank if not changing"
          />
        </div>

        <div className="profile-field">
          <label>Confirm Password</label>

          <input
            type="password"
            name="confirmPassword"
            value={form.confirmPassword}
            onChange={handleChange}
            placeholder="Re-enter new password"
          />
        </div>

        <button
          type="submit"
          disabled={saving}
          style={{
            backgroundColor: "#2563eb",
            color: "#fff",
            border: "none",
            padding: "12px 24px",
            borderRadius: "8px",
            cursor: saving
              ? "not-allowed"
              : "pointer",
            opacity: saving ? 0.7 : 1,
            transition: "0.2s",
          }}
        >
          {saving ? "Saving..." : "Save Changes"}
        </button>
      </form>

      {showOtp && (
        <div
          style={{
            position: "fixed",
            inset: 0,
            background: "rgba(0,0,0,.5)",
            display: "flex",
            alignItems: "center",
            justifyContent: "center",
            zIndex: 9999,
          }}
        >
          <div
            style={{
              background: "#fff",
              padding: "25px",
              borderRadius: "10px",
              width: "350px",
            }}
          >
            <h3>Verify New Email</h3>

            <p>
              Enter the verification code sent to
              your new email address.
            </p>

            <input
              type="text"
              value={otp}
              onChange={(e) =>
                setOtp(e.target.value)
              }
              placeholder="6-digit OTP"
              style={{
                width: "100%",
                padding: "10px",
                marginTop: "10px",
              }}
            />

            <div
              style={{
                display: "flex",
                gap: "10px",
                marginTop: "20px",
              }}
            >
              <button
                type="button"
                onClick={handleOtpVerification}
              >
                Verify
              </button>

              <button
                type="button"
                onClick={() => {
                  setShowOtp(false);
                  setOtp("");
                }}
              >
                Cancel
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );

  return isAdmin ? (
    <DashboardLayout>
      {profileContent}
    </DashboardLayout>
  ) : (
    profileContent
  );
}

export default ProfilePage;