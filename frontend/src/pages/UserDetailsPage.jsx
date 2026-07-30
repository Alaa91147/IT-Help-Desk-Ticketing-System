import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";

import DashboardLayout from "../components/Dashboard/DashboardLayout";

import { useAuth } from "../context/AuthContext";

import {
    getUser,
    activateUser,
    deactivateUser,
    updateUserRole,
    updateUser,
} from "../api/userManagementApi";

import "../styles/userDetails.css";

function UserDetailsPage() {
    const { id } = useParams();

    const navigate = useNavigate();

    const { token, user: loggedInUser } = useAuth();     
    
    const [user, setUser] = useState(null);

    const isOwnProfile = loggedInUser?.id === user?.id;

    const [loading, setLoading] = useState(true);
    const [editing, setEditing] = useState(false);

    const [showRoleModal, setShowRoleModal] = useState(false);

    const [selectedRole, setSelectedRole] = useState("");

    const [formData, setFormData] = useState({
        firstName: "",
        lastName: "",
        email: "",
        phoneNumber: "",
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
    });

    useEffect(() => {
        async function loadUser() {
            try {
                const response = await getUser(id, token);
                const data = response.data;

                setUser(data);

                setSelectedRole(data.role?.roleName || "User");

            setFormData({
                firstName: data.firstName,
                lastName: data.lastName,
                email: data.email,
                phoneNumber: data.phoneNumber || "",
                currentPassword: "",
                newPassword: "",
                confirmPassword: "",
            });
            } catch (error) {
                console.error(error);
            } finally {
                setLoading(false);
            }
        }

        loadUser();
    }, [id, token]);

    function handleChange(e) {
        const { name, value } = e.target;

        setFormData((prev) => ({
            ...prev,
            [name]: value,
        }));
    }
    async function handleSave() {
    try {
        await updateUser(id, formData, token);

        const response = await getUser(id, token);

        setUser(response.data);

        setEditing(false);
    } catch (error) {
        console.error(error);
    }
}

function handleCancel() {
    setEditing(false);

    setFormData({
        firstName: user.firstName || "",
        lastName: user.lastName || "",
        email: user.email || "",
        phoneNumber: user.phoneNumber || "",
        currentPassword: "",
        newPassword: "",
        confirmPassword: "",
    });
}

async function handleActivate() {
    try {
        await activateUser(id, token);

        const response = await getUser(id, token);

        setUser(response.data);
    } catch (error) {
        console.error(error);
    }
}

async function handleDeactivate() {
    try {
        await deactivateUser(id, token);

        const response = await getUser(id, token);

        setUser(response.data);
    } catch (error) {
        console.error(error);
    }
}

async function handleRoleSave() {
    try {
        await updateUserRole(id, selectedRole, token);

        const response = await getUser(id, token);

        setUser(response.data);
        setSelectedRole(response.data.role?.roleName || "User");
        setShowRoleModal(false);
    } catch (error) {
        console.error(error);
    }
}

    if (loading) {
        return (
            <DashboardLayout>
                <div className="user-details-container">
                    <div className="user-card">
                        <h2>Loading...</h2>
                    </div>
                </div>
            </DashboardLayout>
        );
    }


    if (!user) {
        return (
            <DashboardLayout>
                <div className="user-details-container">
                    <div className="user-card">
                        <h2>User not found.</h2>
                    </div>
                </div>
            </DashboardLayout>
        );
    }

    return (
        <DashboardLayout>
            <div className="user-details-container">

                <button
                    className="back-btn"
                    onClick={() => navigate("/users")}
                >
                    ← Back to Users
                </button>

                <div className="user-card">

                    <h2>User Details</h2>

                    <div className="user-row">
                        <span className="user-label">User ID</span>
                        <span className="user-value">{user.id}</span>
                    </div>

                    <div className="user-row">
                        <span className="user-label">First Name</span>

                        {editing ? (
                            <input
                                name="firstName"
                                value={formData.firstName}
                                onChange={handleChange}
                            />
                        ) : (
                            <span className="user-value">{user.firstName}</span>
                        )}
                    </div>

                    <div className="user-row">
                        <span className="user-label">Last Name</span>

                        {editing ? (
                            <input
                                name="lastName"
                                value={formData.lastName}
                                onChange={handleChange}
                            />
                        ) : (
                            <span className="user-value">{user.lastName}</span>
                        )}
                    </div>

                    <div className="user-row">
                        <span className="user-label">Email</span>

                        {editing ? (
                            isOwnProfile ? (
                                <input
                                    name="email"
                                    value={formData.email}
                                    onChange={handleChange}
                                />
                            ) : (
                                <span className="user-value">
                                    {user.email}
                                    <br />
                                    <small style={{ color: "#888" }}>
                                        Only the account owner can change their email.
                                    </small>
                                </span>
                            )
                        ) : (
                            <span className="user-value">{user.email}</span>
                        )}
                    </div>

                    <div className="user-row">
                        <span className="user-label">Phone</span>

                        {editing ? (
                            <input
                                name="phoneNumber"
                                value={formData.phoneNumber}
                                onChange={handleChange}
                            />
                        ) : (
                            <span className="user-value">
                                {user.phoneNumber || "-"}
                            </span>
                        )}
                    </div>

                    {editing && isOwnProfile && (
                        <>
                            <div className="user-row">
                                <span className="user-label">Current Password</span>

                                <input
                                    type="password"
                                    name="currentPassword"
                                    value={formData.currentPassword}
                                    onChange={handleChange}
                                    placeholder="Enter your current password"
                                />
                            </div>

                            <div className="user-row">
                                <span className="user-label">New Password</span>

                                <input
                                    type="password"
                                    name="newPassword"
                                    value={formData.newPassword}
                                    onChange={handleChange}
                                    placeholder="Leave blank if not changing"
                                />
                            </div>

                            <div className="user-row">
                                <span className="user-label">Confirm Password</span>

                                <input
                                    type="password"
                                    name="confirmPassword"
                                    value={formData.confirmPassword}
                                    onChange={handleChange}
                                    placeholder="Re-enter new password"
                                />
                            </div>
                        </>
                    )}

                    <div className="user-row">
                        <span className="user-label">Role</span>
                        <span className="user-value">
                            {user.role?.roleName || "-"}
                        </span>
                    </div>

                    <div className="user-row">
                        <span className="user-label">Status</span>

                        <span
                            className={
                                user.isActive
                                    ? "status-active"
                                    : "status-inactive"
                            }
                        >
                            {user.isActive ? "Active" : "Deactivated"}
                        </span>
                    </div>

                    {!user.isActive && (
                        <div className="deactivated-box">
                            This account is currently deactivated and cannot log
                            in until an administrator activates it.
                        </div>
                    )}

                    <div className="action-buttons">

                        <button
                            className="edit-btn"
                            onClick={() => {
                                if (editing) {
                                    handleSave();
                                } else {
                                    setEditing(true);
                                }
                            }}
                        >
                            {editing ? "💾 Save Changes" : "✏ Edit User"}
                        </button>

                        <button
                            className="role-btn"
                            onClick={() => setShowRoleModal(true)}
                        >
                            👤 Change Role
                        </button>

                        {user.isActive ? (
                            <button
                                className="deactivate-btn"
                                onClick={handleDeactivate}
                            >
                                🔒 Deactivate Account
                            </button>
                        ) : (
                            <button
                                className="activate-btn"
                                onClick={handleActivate}
                            >
                                ✅ Activate Account
                            </button>
                        )}

                    </div>

                </div>
                
            {showRoleModal && (
                <div className="role-modal-overlay">
                    <div className="role-modal">

                        <h2>Change User Role</h2>

                        <label>Current Role</label>
                        <p>{user.role?.roleName}</p>

                        <label>Select New Role</label>

                        <select
                            value={selectedRole}
                            onChange={(e) => setSelectedRole(e.target.value)}
                        >
                            <option value="Admin">Admin</option>
                            <option value="Manager">Manager</option>
                            <option value="SupportAgent">Support Agent</option>
                            <option value="User">User</option>
                        </select>

                        <div className="modal-buttons">

                            <button
                                className="cancel-btn"
                                onClick={() => setShowRoleModal(false)}
                            >
                                Cancel
                            </button>

                            <button
                                className="save-btn"
                                onClick={handleRoleSave}
                            >
                                Save
                            </button>

                        </div>

                    </div>
                </div>
            )}
                        </div>
        </DashboardLayout>
    );
}

export default UserDetailsPage;