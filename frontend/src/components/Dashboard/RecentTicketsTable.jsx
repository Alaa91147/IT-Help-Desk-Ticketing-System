import { useNavigate } from "react-router";

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

function personName(person) {
  if (!person) return "Unassigned";

  return (
    person.fullName ||
    `${person.firstName || ""} ${
      person.lastName || ""
    }`.trim() ||
    person.email
  );
}

function RecentTicketsTable({ tickets, isLoading }) {
  const navigate = useNavigate();

  return (
    <section className="dashboard-table-card">
      <div className="table-heading">
        <div>
          <h2>Recent tickets</h2>
          <span>Latest help-desk requests</span>
        </div>
        <button
          type="button"
          className="table-link"
          onClick={() => navigate("/tickets")}
        >
          View all
        </button>
      </div>

      <div className="dashboard-table-scroll">
        <table>
          <thead>
            <tr>
              <th>Ticket</th>
              <th>Subject</th>
              <th>Status</th>
              <th>Priority</th>
              <th>Assigned to</th>
              <th>Due</th>
              <th />
            </tr>
          </thead>
          <tbody>
            {isLoading && (
              <tr>
                <td colSpan="7" className="empty-table">
                  Loading recent tickets…
                </td>
              </tr>
            )}

            {!isLoading &&
              tickets.map((ticket) => (
                <tr key={ticket.id}>
                  <td>
                    <strong>{ticket.ticketNumber}</strong>
                  </td>
                  <td>{ticket.subject}</td>
                  <td>
                    <span
                      className={`dashboard-pill status-${(
                        ticket.status?.statusName || ""
                      ).toLowerCase()}`}
                    >
                      {ticket.status?.statusName === "InProgress"
                        ? "In Progress"
                        : ticket.status?.statusName}
                    </span>
                  </td>
                  <td>{ticket.priority?.priorityName || "—"}</td>
                  <td>
                    {personName(
                      ticket.assignedUser ||
                        ticket.assigned_user
                    )}
                  </td>
                  <td>{formatDate(ticket.dueAt)}</td>
                  <td>
                    <button
                      type="button"
                      className="view-ticket-button"
                      onClick={() =>
                        navigate(`/tickets/${ticket.id}`)
                      }
                    >
                      View
                    </button>
                  </td>
                </tr>
              ))}

            {!isLoading && tickets.length === 0 && (
              <tr>
                <td colSpan="7" className="empty-table">
                  No tickets are available.
                </td>
              </tr>
            )}
          </tbody>
        </table>
      </div>
    </section>
  );
}

export default RecentTicketsTable;