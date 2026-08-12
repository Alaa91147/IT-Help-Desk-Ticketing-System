import { useCallback, useEffect, useState } from "react";
import { useNavigate } from "react-router";

import {
  acceptAgentRequest,
  getPendingAgentRequests,
  rejectAgentRequest,
} from "../api/agentRequestApi";
import DashboardLayout from "../components/Dashboard/DashboardLayout";
import { useAuth } from "../context/AuthContext";

function personName(person, fallback = "Unknown") {
  if (!person) return fallback;
  const name = `${person.firstName || ""} ${person.lastName || ""}`.trim();
  return name || person.email || fallback;
}

function formatDate(value) {
  if (!value) return "—";
  return new Date(value).toLocaleString("en-GB", {
    day: "2-digit",
    month: "short",
    year: "numeric",
    hour: "2-digit",
    minute: "2-digit",
  });
}

export default function AgentRequestsPage() {
  const navigate = useNavigate();
  const { token } = useAuth();
  const [requests, setRequests] = useState([]);
  const [loading, setLoading] = useState(true);
  const [workingId, setWorkingId] = useState(null);
  const [error, setError] = useState("");
  const [success, setSuccess] = useState("");

  const loadRequests = useCallback(async () => {
    try {
      setLoading(true);
      setError("");
      const response = await getPendingAgentRequests(token);
      setRequests(Array.isArray(response?.data) ? response.data : []);
    } catch (requestError) {
      setError(requestError.message || "Unable to load agent requests.");
    } finally {
      setLoading(false);
    }
  }, [token]);

  useEffect(() => {
    loadRequests();
  }, [loadRequests]);

  async function decide(ticket, action) {
    const actionLabel = action === "accept" ? "accept" : "reject";
    if (!window.confirm(`Are you sure you want to ${actionLabel} this request?`)) {
      return;
    }

    try {
      setWorkingId(ticket.id);
      setError("");
      setSuccess("");

      if (action === "accept") {
        await acceptAgentRequest(ticket.id, token);
        setSuccess(
          `${ticket.ticketNumber} was assigned to ${personName(ticket.requester)}.`
        );
      } else {
        await rejectAgentRequest(ticket.id, token);
        setSuccess(`The request for ${ticket.ticketNumber} was rejected.`);
      }

      setRequests((current) =>
        current.filter((request) => request.id !== ticket.id)
      );
    } catch (requestError) {
      setError(requestError.message || `Unable to ${actionLabel} request.`);
      await loadRequests();
    } finally {
      setWorkingId(null);
    }
  }

  return (
    <DashboardLayout>
      <div style={styles.page}>
        <header style={styles.header}>
          <div>
            <span style={styles.eyebrow}>ADMIN WORKFLOW</span>
            <h1 style={styles.title}>Agent ticket requests</h1>
            <p style={styles.subtitle}>
              Review requests from Support Agents for available tickets.
            </p>
          </div>
          <button style={styles.refreshButton} onClick={loadRequests} disabled={loading}>
            {loading ? "Loading…" : "Refresh"}
          </button>
        </header>

        {error && <div style={styles.error}>{error}</div>}
        {success && <div style={styles.success}>{success}</div>}

        <section style={styles.summary}>
          <span>Pending requests</span>
          <strong>{requests.length}</strong>
        </section>

        {loading ? (
          <div style={styles.empty}>Loading requests…</div>
        ) : requests.length === 0 ? (
          <div style={styles.empty}>
            <strong>No pending requests</strong>
            <p>New Support Agent requests will appear here.</p>
          </div>
        ) : (
          <div style={styles.grid}>
            {requests.map((ticket) => (
              <article key={ticket.id} style={styles.card}>
                <div style={styles.cardHeader}>
                  <div>
                    <span style={styles.ticketNumber}>{ticket.ticketNumber}</span>
                    <h2 style={styles.subject}>{ticket.subject}</h2>
                  </div>
                  <span style={styles.pendingBadge}>Pending</span>
                </div>

                <p style={styles.description}>{ticket.description}</p>

                <dl style={styles.facts}>
                  <div><dt>Requesting agent</dt><dd>{personName(ticket.requester)}</dd></div>
                  <div><dt>Requested</dt><dd>{formatDate(ticket.agent_requested_at)}</dd></div>
                  <div><dt>Created by</dt><dd>{personName(ticket.user)}</dd></div>
                  <div><dt>Category</dt><dd>{ticket.category?.categoryName || "—"}</dd></div>
                  <div><dt>Priority</dt><dd>{ticket.priority?.priorityName || "—"}</dd></div>
                  <div><dt>Status</dt><dd>{ticket.status?.statusName || "—"}</dd></div>
                </dl>

                <div style={styles.actions}>
                  <button
                    type="button"
                    style={styles.viewButton}
                    onClick={() => navigate(`/tickets/${ticket.id}`)}
                  >
                    View ticket
                  </button>
                  <button
                    type="button"
                    style={styles.rejectButton}
                    disabled={workingId === ticket.id}
                    onClick={() => decide(ticket, "reject")}
                  >
                    Reject
                  </button>
                  <button
                    type="button"
                    style={styles.acceptButton}
                    disabled={workingId === ticket.id}
                    onClick={() => decide(ticket, "accept")}
                  >
                    {workingId === ticket.id ? "Processing…" : "Accept & assign"}
                  </button>
                </div>
              </article>
            ))}
          </div>
        )}
      </div>
    </DashboardLayout>
  );
}

const styles = {
  page: { width: "min(1150px, 100%)", margin: "0 auto" },
  header: { display: "flex", justifyContent: "space-between", alignItems: "flex-end", gap: 20, marginBottom: 22 },
  eyebrow: { color: "#2563eb", fontSize: 12, fontWeight: 800, letterSpacing: ".1em" },
  title: { margin: "7px 0", color: "#172033", fontSize: "clamp(28px, 4vw, 40px)" },
  subtitle: { margin: 0, color: "#667085" },
  refreshButton: { padding: "10px 17px", border: "1px solid #cbd5e1", borderRadius: 9, background: "#fff", fontWeight: 700, cursor: "pointer" },
  summary: { display: "flex", justifyContent: "space-between", alignItems: "center", marginBottom: 18, padding: 18, border: "1px solid #dbe3ee", borderRadius: 12, background: "#fff" },
  error: { marginBottom: 16, padding: 14, borderRadius: 9, color: "#991b1b", background: "#fef2f2", border: "1px solid #fecaca" },
  success: { marginBottom: 16, padding: 14, borderRadius: 9, color: "#166534", background: "#f0fdf4", border: "1px solid #bbf7d0" },
  empty: { padding: 50, textAlign: "center", color: "#667085", border: "1px solid #dbe3ee", borderRadius: 14, background: "#fff" },
  grid: { display: "grid", gap: 16 },
  card: { padding: 22, border: "1px solid #dbe3ee", borderRadius: 14, background: "#fff", boxShadow: "0 5px 18px rgba(15,23,42,.04)" },
  cardHeader: { display: "flex", justifyContent: "space-between", gap: 16 },
  ticketNumber: { color: "#2563eb", fontWeight: 800, fontSize: 13 },
  subject: { margin: "6px 0 0", color: "#172033", fontSize: 21 },
  pendingBadge: { height: "fit-content", padding: "5px 10px", borderRadius: 999, color: "#92400e", background: "#fef3c7", fontSize: 12, fontWeight: 800 },
  description: { color: "#475467", lineHeight: 1.55 },
  facts: { display: "grid", gridTemplateColumns: "repeat(auto-fit, minmax(160px, 1fr))", gap: 12, margin: "18px 0" },
  actions: { display: "flex", justifyContent: "flex-end", flexWrap: "wrap", gap: 10, paddingTop: 16, borderTop: "1px solid #e2e8f0" },
  viewButton: { padding: "10px 15px", border: "1px solid #cbd5e1", borderRadius: 8, background: "#fff", fontWeight: 700, cursor: "pointer" },
  rejectButton: { padding: "10px 15px", border: "1px solid #fecaca", borderRadius: 8, color: "#b91c1c", background: "#fff", fontWeight: 700, cursor: "pointer" },
  acceptButton: { padding: "10px 15px", border: 0, borderRadius: 8, color: "#fff", background: "#2563eb", fontWeight: 700, cursor: "pointer" },
};