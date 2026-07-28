import { useNavigate } from "react-router";

function RecentTicketsTable({ tickets, isLoading }) {
  const navigate = useNavigate();

  return (
    <div className="tickets-table">
      <div className="table-heading">
        <h2>Recent Tickets</h2>
        <button
          type="button"
          className="table-link"
          onClick={() => navigate("/tickets")}
        >
          View all tickets
        </button>
      </div>

      <table>
        <thead>
          <tr>
            <th>ID</th>
            <th>Subject</th>
            <th>Status</th>
            <th>Priority</th>
            <th>Action</th>
          </tr>
        </thead>

        <tbody>
          {isLoading && (
            <tr>
              <td colSpan="5" className="empty-table">
                Loading recent tickets...
              </td>
            </tr>
          )}

          {!isLoading &&
            tickets.map((ticket) => {
              const status =
                ticket?.status?.statusName || "Unknown";
              return (
                <tr key={ticket.id}>
                  <td>{ticket.ticketNumber || ticket.id}</td>
                  <td>{ticket.subject}</td>
                  <td>
                    {status === "InProgress"
                      ? "In Progress"
                      : status}
                  </td>
                  <td>
                    {ticket?.priority?.priorityName || "Unknown"}
                  </td>
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
              );
            })}

          {!isLoading && tickets.length === 0 && (
            <tr>
              <td colSpan="5" className="empty-table">
                No tickets are available.
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}

export default RecentTicketsTable;