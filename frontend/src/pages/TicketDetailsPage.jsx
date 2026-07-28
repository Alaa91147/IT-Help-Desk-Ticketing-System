import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";

import { getCategories, getPriorities } from "../api/lookupApi";
import {
  deleteTicket,
  getTicketById,
  updateTicket,
} from "../api/ticketApi";
import { useAuth } from "../context/AuthContext";

function extractArray(response) {
  if (Array.isArray(response)) return response;
  if (Array.isArray(response?.data)) return response.data;
  if (Array.isArray(response?.data?.data)) return response.data.data;
  return [];
}

function getRole(user) {
  return user?.role?.roleName || user?.roleName || user?.role || "";
}

function getPersonName(person) {
  if (!person) return "Unassigned";
  return (
    person.fullName ||
    `${person.firstName || ""} ${person.lastName || ""}`.trim() ||
    person.email ||
    "Unknown"
  );
}

function TicketDetailsPage() {
  const { ticketId } = useParams();
  const navigate = useNavigate();
  const { token, user } = useAuth();

  const [ticket, setTicket] = useState(null);
  const [categories, setCategories] = useState([]);
  const [priorities, setPriorities] = useState([]);
  const [formData, setFormData] = useState({
    categoryId: "",
    priorityId: "",
    subject: "",
    description: "",
  });
  const [isEditing, setIsEditing] = useState(false);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [successMessage, setSuccessMessage] = useState("");

  async function loadTicket() {
    try {
      setIsLoading(true);
      setErrorMessage("");
      const response = await getTicketById(ticketId, token);
      const loadedTicket = response?.data || null;
      setTicket(loadedTicket);
      setFormData({
        categoryId: loadedTicket?.category?.id || loadedTicket?.categoryId || "",
        priorityId: loadedTicket?.priority?.id || loadedTicket?.priorityId || "",
        subject: loadedTicket?.subject || "",
        description: loadedTicket?.description || "",
      });
    } catch (error) {
      setErrorMessage(error.message || "Unable to load ticket.");
    } finally {
      setIsLoading(false);
    }
  }

  useEffect(() => {
    loadTicket();
    // ticketId and token identify the requested protected resource.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [ticketId, token]);

  async function beginEditing() {
    setErrorMessage("");
    setSuccessMessage("");
    try {
      const [categoryResponse, priorityResponse] = await Promise.all([
        getCategories(token),
        getPriorities(token),
      ]);
      setCategories(extractArray(categoryResponse));
      setPriorities(extractArray(priorityResponse));
      setIsEditing(true);
    } catch (error) {
      setErrorMessage(
        error.message || "Unable to load the ticket form."
      );
    }
  }

  async function handleUpdate(event) {
    event.preventDefault();
    if (
      !formData.categoryId ||
      !formData.priorityId ||
      !formData.subject.trim() ||
      !formData.description.trim()
    ) {
      setErrorMessage("All ticket fields are required.");
      return;
    }

    try {
      setIsSaving(true);
      setErrorMessage("");
      await updateTicket(ticketId, formData, token);
      setSuccessMessage("Ticket updated successfully.");
      setIsEditing(false);
      await loadTicket();
    } catch (error) {
      const backendErrors = error?.data?.errors;
      const firstValidationError = backendErrors
        ? Object.values(backendErrors).flat()[0]
        : null;
      setErrorMessage(
        firstValidationError ||
          error.message ||
          "Unable to update ticket."
      );
    } finally {
      setIsSaving(false);
    }
  }

  async function handleDelete() {
    const confirmed = window.confirm(
      "Delete this ticket permanently? This action cannot be undone."
    );
    if (!confirmed) return;

    try {
      setIsSaving(true);
      setErrorMessage("");
      await deleteTicket(ticketId, token);
      navigate("/tickets", {
        replace: true,
        state: { message: "Ticket deleted successfully." },
      });
    } catch (error) {
      setErrorMessage(error.message || "Unable to delete ticket.");
      setIsSaving(false);
    }
  }

  if (isLoading) {
    return <div style={styles.centeredPage}>Loading ticket...</div>;
  }

  if (errorMessage && !ticket) {
    return (
      <div style={styles.centeredPage}>
        <div style={styles.card}>
          <h2>Unable to open ticket</h2>
          <p style={styles.error}>{errorMessage}</p>
          <button style={styles.primaryButton} onClick={() => navigate("/tickets")}>
            Back to Tickets
          </button>
        </div>
      </div>
    );
  }

  const role = getRole(user);
  const ownerId =
    ticket?.user?.id || ticket?.createdByUser?.id || ticket?.userId;
  const statusName =
    ticket?.status?.statusName || ticket?.statusName || "Unknown";
  const isOwner = Number(ownerId) === Number(user?.id);
  const canModify =
    role === "Admin" || (role === "User" && isOwner && statusName === "Open");

  return (
    <div style={styles.page}>
      <div style={styles.container}>
        <button style={styles.backButton} onClick={() => navigate("/tickets")}>
          ← Back to Tickets
        </button>

        {errorMessage && <div style={styles.errorAlert}>{errorMessage}</div>}
        {successMessage && (
          <div style={styles.successAlert}>{successMessage}</div>
        )}

        <div style={styles.card}>
          <div style={styles.header}>
            <div>
              <span style={styles.ticketNumber}>{ticket.ticketNumber}</span>
              <h1 style={styles.title}>{ticket.subject}</h1>
            </div>
            <span style={styles.statusBadge}>
              {statusName === "InProgress" ? "In Progress" : statusName}
            </span>
          </div>

          {isEditing ? (
            <form style={styles.form} onSubmit={handleUpdate}>
              <label style={styles.field}>
                <span>Category</span>
                <select
                  value={formData.categoryId}
                  onChange={(event) =>
                    setFormData({ ...formData, categoryId: event.target.value })
                  }
                  style={styles.input}
                  required
                >
                  <option value="">Select category</option>
                  {categories.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.categoryName}
                    </option>
                  ))}
                </select>
              </label>

              <label style={styles.field}>
                <span>Priority</span>
                <select
                  value={formData.priorityId}
                  onChange={(event) =>
                    setFormData({ ...formData, priorityId: event.target.value })
                  }
                  style={styles.input}
                  required
                >
                  <option value="">Select priority</option>
                  {priorities.map((item) => (
                    <option key={item.id} value={item.id}>
                      {item.priorityName}
                    </option>
                  ))}
                </select>
              </label>

              <label style={styles.field}>
                <span>Subject</span>
                <input
                  value={formData.subject}
                  maxLength={255}
                  onChange={(event) =>
                    setFormData({ ...formData, subject: event.target.value })
                  }
                  style={styles.input}
                  required
                />
              </label>

              <label style={styles.field}>
                <span>Description</span>
                <textarea
                  value={formData.description}
                  rows="7"
                  onChange={(event) =>
                    setFormData({
                      ...formData,
                      description: event.target.value,
                    })
                  }
                  style={styles.input}
                  required
                />
              </label>

              <div style={styles.actions}>
                <button
                  type="submit"
                  style={styles.primaryButton}
                  disabled={isSaving}
                >
                  {isSaving ? "Saving..." : "Save Changes"}
                </button>
                <button
                  type="button"
                  style={styles.secondaryButton}
                  onClick={() => setIsEditing(false)}
                  disabled={isSaving}
                >
                  Cancel
                </button>
              </div>
            </form>
          ) : (
            <>
              <div style={styles.grid}>
                <Detail label="Created By" value={getPersonName(ticket.user)} />
                <Detail
                  label="Assigned To"
                  value={getPersonName(ticket.assignedUser)}
                />
                <Detail
                  label="Category"
                  value={ticket?.category?.categoryName || "—"}
                />
                <Detail
                  label="Priority"
                  value={ticket?.priority?.priorityName || "—"}
                />
              </div>

              <div style={styles.descriptionSection}>
                <h2>Description</h2>
                <p style={styles.description}>{ticket.description}</p>
              </div>

              {canModify && (
                <div style={styles.actions}>
                  <button style={styles.primaryButton} onClick={beginEditing}>
                    Edit Ticket
                  </button>
                  <button
                    style={styles.deleteButton}
                    onClick={handleDelete}
                    disabled={isSaving}
                  >
                    {isSaving ? "Deleting..." : "Delete Ticket"}
                  </button>
                </div>
              )}

              {role === "User" && isOwner && statusName !== "Open" && (
                <p style={styles.info}>
                  This ticket can no longer be edited or deleted because work
                  has started.
                </p>
              )}
            </>
          )}
        </div>
      </div>
    </div>
  );
}

function Detail({ label, value }) {
  return (
    <div style={styles.detailItem}>
      <span style={styles.detailLabel}>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

const styles = {
  page: {
    minHeight: "100vh",
    padding: "32px 20px",
    backgroundColor: "#f4f7fb",
    fontFamily: "Arial, sans-serif",
  },
  centeredPage: {
    minHeight: "100vh",
    display: "flex",
    justifyContent: "center",
    alignItems: "center",
    backgroundColor: "#f4f7fb",
  },
  container: { maxWidth: "1000px", margin: "0 auto" },
  card: {
    padding: "32px",
    border: "1px solid #e4e7ec",
    borderRadius: "14px",
    backgroundColor: "#fff",
  },
  header: {
    display: "flex",
    justifyContent: "space-between",
    gap: "20px",
    marginBottom: "28px",
    flexWrap: "wrap",
  },
  ticketNumber: { color: "#2563eb", fontWeight: 700 },
  title: { margin: "8px 0 0", color: "#172033" },
  statusBadge: {
    alignSelf: "flex-start",
    padding: "7px 12px",
    borderRadius: "20px",
    backgroundColor: "#eef2ff",
    color: "#3730a3",
    fontWeight: 700,
  },
  backButton: {
    marginBottom: "18px",
    border: "none",
    background: "transparent",
    color: "#2563eb",
    fontWeight: 700,
    cursor: "pointer",
  },
  grid: {
    display: "grid",
    gridTemplateColumns: "repeat(auto-fit, minmax(200px, 1fr))",
    gap: "16px",
  },
  detailItem: {
    padding: "16px",
    border: "1px solid #eaecf0",
    borderRadius: "10px",
    backgroundColor: "#f9fafb",
  },
  detailLabel: {
    display: "block",
    marginBottom: "6px",
    color: "#667085",
    fontSize: "13px",
  },
  descriptionSection: {
    marginTop: "28px",
    paddingTop: "20px",
    borderTop: "1px solid #eaecf0",
  },
  description: { lineHeight: 1.7, whiteSpace: "pre-wrap" },
  form: { display: "grid", gap: "18px" },
  field: { display: "grid", gap: "7px", fontWeight: 700 },
  input: {
    width: "100%",
    padding: "11px",
    border: "1px solid #cfd5df",
    borderRadius: "8px",
    font: "inherit",
  },
  actions: {
    display: "flex",
    gap: "12px",
    marginTop: "24px",
    flexWrap: "wrap",
  },
  primaryButton: {
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#2563eb",
    color: "#fff",
    fontWeight: 700,
    cursor: "pointer",
  },
  secondaryButton: {
    padding: "11px 18px",
    border: "1px solid #cfd5df",
    borderRadius: "8px",
    backgroundColor: "#fff",
    fontWeight: 700,
    cursor: "pointer",
  },
  deleteButton: {
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#b42318",
    color: "#fff",
    fontWeight: 700,
    cursor: "pointer",
  },
  errorAlert: {
    marginBottom: "16px",
    padding: "12px",
    borderRadius: "8px",
    backgroundColor: "#fee4e2",
    color: "#912018",
  },
  successAlert: {
    marginBottom: "16px",
    padding: "12px",
    borderRadius: "8px",
    backgroundColor: "#dcfae6",
    color: "#05603a",
  },
  error: { color: "#b42318" },
  info: {
    marginTop: "22px",
    padding: "12px",
    borderRadius: "8px",
    backgroundColor: "#eff6ff",
    color: "#1e40af",
  },
};

export default TicketDetailsPage;