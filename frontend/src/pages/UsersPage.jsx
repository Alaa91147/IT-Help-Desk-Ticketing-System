import { useEffect, useState } from "react";
import DashboardLayout from "../components/Dashboard/DashboardLayout";
import { useAuth } from "../context/AuthContext";
import { getUsers } from "../api/userManagementApi";
import { useNavigate } from "react-router";

function UsersPage() {
    const { token } = useAuth();
    const navigate = useNavigate();

    const [users, setUsers] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        async function loadUsers() {
            try {
                const response = await getUsers(token);

                setUsers(response.data ?? []);
            } catch (error) {
                console.error(error);
            } finally {
                setLoading(false);
            }
        }

        loadUsers();
    }, [token]);

    return (
        <DashboardLayout>

            <h2>Users</h2>

            <table className="users-table">

                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                    {loading ? (

                        <tr>
                            <td colSpan="6">
                                Loading...
                            </td>
                        </tr>

                    ) : (

                        users.map(user => (

                            <tr key={user.id}>

                                <td>{user.fullName}</td>

                                <td>{user.email}</td>

                                <td>{user.phoneNumber}</td>

                                <td>{user.role?.roleName}</td>

                                <td>
                                    {user.isActive ? "Active" : "Inactive"}
                                </td>

                                <td>
                                    <button
                                        onClick={() => navigate(`/users/${user.id}`)}
                                    >
                                        View
                                    </button>
                                </td>

                            </tr>

                        ))

                    )}

                </tbody>

            </table>

        </DashboardLayout>
    );
}

export default UsersPage;