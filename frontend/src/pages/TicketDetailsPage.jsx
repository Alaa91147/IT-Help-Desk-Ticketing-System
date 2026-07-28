import { useEffect, useState } from "react";
import { useNavigate, useParams } from "react-router";

import { getTicketById } from "../api/ticketApi";
import { useAuth } from "../context/AuthContext";

function TicketDetailsPage() {
  const { ticketId } = useParams();
  const navigate = useNavigate();
  const { token } = useAuth();

  const [ticket, setTicket] = useState(null);
  const [isLoading, setIsLoading] = useState(true);
  const [errorMessage, setErrorMessage] =
    useState("");

  useEffect(() => {
    async function loadTicket() {
      try {
        setIsLoading(true);
        setErrorMessage("");

        const response = await getTicketById(
          ticketId,
          token
        );

        setTicket(response?.data || null);
      } catch (error) {
        setErrorMessage(
          error.message || "Unable to load ticket."
        );
      } finally {
        setIsLoading(false);
      }
    }

    loadTicket();
  }, [ticketId, token]);

  function formatDate(dateValue) {
    if (!dateValue) {
      return "—";
    }

    return new Date(dateValue).toLocaleString(
      "en-GB",
      {
        dateStyle: "medium",
        timeStyle: "short",
      }
    );
  }

  function getPersonName(person) {
    if (!person) {
      return "Unassigned";
    }

    return (
      person.fullName ||
      `${person.firstName || ""} ${
        person.lastName || ""
      }`.trim() ||
      person.email ||
      "Unknown"
    );
  }

  if (isLoading) {
    return (
      <div style={styles.centeredPage}>
        Loading ticket...
      </div>
    );
  }

  if (errorMessage || !ticket) {
    return (
      <div style={styles.centeredPage}>
        <div style={styles.errorCard}>
          <h2>Unable to open ticket</h2>
          <p>{errorMessage}</p>

          <button
            type="button"
            style={styles.primaryButton}
            onClick={() => navigate("/tickets")}
          >
            Back to Tickets
          </button>
        </div>
      </div>
    );
  }

  const statusName =
    ticket?.status?.statusName || "Unknown";

  return (
    <div style={styles.page}>
      <div style={styles.container}>
        <button
          type="button"
          style={styles.backButton}
          onClick={() => navigate("/tickets")}
        >
          ← Back to Tickets
        </button>

        <div style={styles.card}>
          <div style={styles.header}>
            <div>
              <span style={styles.ticketNumber}>
                {ticket.ticketNumber}
              </span>

              <h1 style={styles.title}>
                {ticket.subject}
              </h1>
            </div>

            <span style={styles.statusBadge}>
              {statusName === "InProgress"
                ? "In Progress"
                : statusName}
            </span>
          </div>

          <div style={styles.grid}>
            <DetailItem
              label="Created By"
              value={getPersonName(ticket.user)}
            />

            <DetailItem
              label="Assigned To"
              value={getPersonName(
                ticket.assignedUser
              )}
            />

            <DetailItem
              label="Category"
              value={
                ticket?.category?.categoryName ||
                "—"
              }
            />

            <DetailItem
              label="Priority"
              value={
                ticket?.priority?.priorityName ||
                "—"
              }
            />

            <DetailItem
              label="Created"
              value={formatDate(ticket.createdAt)}
            />

            <DetailItem
              label="Updated"
              value={formatDate(ticket.updatedAt)}
            />

            <DetailItem
              label="Resolved"
              value={formatDate(ticket.resolvedAt)}
            />

            <DetailItem
              label="Closed"
              value={formatDate(ticket.closedAt)}
            />
          </div>

          <div style={styles.descriptionSection}>
            <h2 style={styles.sectionTitle}>
              Description
            </h2>

            <p style={styles.description}>
              {ticket.description}
            </p>
          </div>
        </div>
      </div>
    </div>
  );
}

function DetailItem({ label, value }) {
  return (
    <div style={styles.detailItem}>
      <span style={styles.detailLabel}>{label}</span>
      <strong style={styles.detailValue}>
        {value}
      </strong>
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
    padding: "20px",
    backgroundColor: "#f4f7fb",
    fontFamily: "Arial, sans-serif",
  },

  container: {
    maxWidth: "1000px",
    margin: "0 auto",
  },

  backButton: {
    marginBottom: "18px",
    border: "none",
    backgroundColor: "transparent",
    color: "#2563eb",
    fontWeight: 700,
    cursor: "pointer",
  },

  card: {
    padding: "32px",
    border: "1px solid #e4e7ec",
    borderRadius: "14px",
    backgroundColor: "#ffffff",
  },

  header: {
    display: "flex",
    justifyContent: "space-between",
    gap: "20px",
    marginBottom: "30px",
    flexWrap: "wrap",
  },

  ticketNumber: {
    color: "#2563eb",
    fontWeight: 700,
  },

  title: {
    margin: "8px 0 0",
    color: "#172033",
  },

  statusBadge: {
    alignSelf: "flex-start",
    padding: "7px 12px",
    borderRadius: "20px",
    backgroundColor: "#eef2ff",
    color: "#3730a3",
    fontWeight: 700,
  },

  grid: {
    display: "grid",
    gridTemplateColumns:
      "repeat(auto-fit, minmax(200px, 1fr))",
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

  detailValue: {
    color: "#344054",
  },

  descriptionSection: {
    marginTop: "28px",
    paddingTop: "24px",
    borderTop: "1px solid #eaecf0",
  },

  sectionTitle: {
    margin: "0 0 12px",
    color: "#172033",
  },

  description: {
    margin: 0,
    color: "#475467",
    lineHeight: 1.7,
    whiteSpace: "pre-wrap",
  },

  errorCard: {
    maxWidth: "500px",
    padding: "30px",
    borderRadius: "12px",
    backgroundColor: "#ffffff",
    textAlign: "center",
  },

  primaryButton: {
    padding: "11px 18px",
    border: "none",
    borderRadius: "8px",
    backgroundColor: "#2563eb",
    color: "#ffffff",
    fontWeight: 700,
    cursor: "pointer",
  },
};

export default TicketDetailsPage;