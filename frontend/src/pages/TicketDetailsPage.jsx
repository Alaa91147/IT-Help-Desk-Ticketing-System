import {
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";
import { useNavigate, useParams } from "react-router";

import {
  getCategories,
  getPriorities,
  getSupportAgents,
} from "../api/lookupApi";
import {
  addTicketComment,
  assignTicket,
  cancelTicket,
  closeTicket,
  deleteTicket,
  deleteTicketAttachment,
  downloadTicketAttachment,
  escalateTicket,
  getTicketAttachments,
  getTicketById,
  getTicketComments,
  pauseTicket,
  resolveTicket,
  resumeTicket,
  startTicket,
  updateTicket,
  uploadTicketAttachment,
} from "../api/ticketApi";
import { useAuth } from "../context/AuthContext";
import "../styles/ticket-details.css";
import { submitAgentRequest } from "../api/agentRequestApi";

function arrayFrom(response) {
  if (Array.isArray(response)) return response;
  if (Array.isArray(response?.data)) return response.data;
  if (Array.isArray(response?.data?.data)) {
    return response.data.data;
  }
  return [];
}

function roleOf(user) {
  return (
    user?.role?.roleName ||
    user?.roleName ||
    user?.role ||
    ""
  );
}

function personName(person, fallback = "Unassigned") {
  if (!person) return fallback;

  return (
    person.fullName ||
    `${person.firstName || ""} ${
      person.lastName || ""
    }`.trim() ||
    person.email ||
    fallback
  );
}

function formatStatus(status) {
  return status === "InProgress"
    ? "In Progress"
    : status || "Unknown";
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

function formatDuration(totalSeconds) {
  const seconds = Math.max(0, Number(totalSeconds) || 0);
  const hours = Math.floor(seconds / 3600);
  const minutes = Math.floor((seconds % 3600) / 60);
  const remainingSeconds = Math.floor(seconds % 60);

  if (hours > 0) return `${hours}h ${minutes}m`;
  if (minutes > 0) return `${minutes}m ${remainingSeconds}s`;
  return `${remainingSeconds}s`;
}

function errorText(error, fallback) {
  const errors = error?.data?.errors;
  const firstValidationError = errors
    ? Object.values(errors).flat()[0]
    : null;

  return firstValidationError || error?.message || fallback;
}

function normalizeTicket(ticket) {
  if (!ticket) return ticket;

  return {
    ...ticket,
    assignedUser:
      ticket.assignedUser || ticket.assigned_user || null,
    activityLogs:
      ticket.activityLogs || ticket.activity_logs || [],
    workSessions:
      ticket.workSessions || ticket.work_sessions || [],
    assignments: (ticket.assignments || []).map(
      (assignment) => ({
        ...assignment,
        assignedUser:
          assignment.assignedUser ||
          assignment.assigned_user ||
          null,
        previousAssignedUser:
          assignment.previousAssignedUser ||
          assignment.previous_assigned_user ||
          null,
        assignedByUser:
          assignment.assignedByUser ||
          assignment.assigned_by_user ||
          null,
      })
    ),
  };
}

function TicketDetailsPage() {
  const { ticketId } = useParams();
  const navigate = useNavigate();
  const { token, user } = useAuth();

  const [ticket, setTicket] = useState(null);
  const [metrics, setMetrics] = useState({});
  const [permissions, setPermissions] = useState({});
  const [comments, setComments] = useState([]);
  const [attachments, setAttachments] = useState([]);
  const [categories, setCategories] = useState([]);
  const [priorities, setPriorities] = useState([]);
  const [agents, setAgents] = useState([]);

  const [classification, setClassification] = useState({
    categoryId: "",
    priorityId: "",
  });
  const [assignment, setAssignment] = useState({
    assignedUserId: "",
    reason: "",
  });
  const [workflowAction, setWorkflowAction] = useState("");
  const [workflowNote, setWorkflowNote] = useState("");
  const [commentText, setCommentText] = useState("");
  const [isInternal, setIsInternal] = useState(false);
  const [replyTo, setReplyTo] = useState(null);
  const [commentFile, setCommentFile] = useState(null);
  const [commentFileInputKey, setCommentFileInputKey] =
    useState(0);
  const [selectedFile, setSelectedFile] = useState(null);
  const [agentRequestMessage, setAgentRequestMessage] =
    useState("");
  const [agentRequestSubmitted, setAgentRequestSubmitted] =
    useState(false);

  const [isLoading, setIsLoading] = useState(true);
  const [isWorking, setIsWorking] = useState(false);
  const [errorMessage, setErrorMessage] = useState("");
  const [successMessage, setSuccessMessage] = useState("");

  const role = roleOf(user);
  const statusName = ticket?.status?.statusName || "";
  const isTerminal = ["Closed", "Cancelled"].includes(statusName);
  const isOwner = Number(ticket?.userId) === Number(user?.id);
  const isCurrentAgent =
    role === "SupportAgent" &&
    Number(ticket?.assignedUserId) === Number(user?.id);
  const canRequestTicket =
    role === "SupportAgent" &&
    !ticket?.assignedUserId &&
    ticket?.agent_request_status !== "requested" &&
    !agentRequestSubmitted &&
    !["Resolved", "Closed", "Cancelled"].includes(
      statusName
    );

  const canManageAssignment = ["Admin", "Manager"].includes(role);
  const canEditClassification =
    role === "Admin" ||
    (role === "User" && isOwner && statusName === "Open");
  const canDelete =
    role === "Admin" ||
    (role === "User" && isOwner && statusName === "Open");
  const canComment =
    !isTerminal &&
    (["Admin", "Manager"].includes(role) ||
      isCurrentAgent ||
      (role === "User" && isOwner));
  const canUseInternal =
    ["Admin", "Manager"].includes(role) || isCurrentAgent;
  const canUpload =
    !isTerminal &&
    (["Admin", "Manager"].includes(role) ||
      isCurrentAgent ||
      (role === "User" && isOwner));

  const activeSession = metrics?.activeWorkSession;

  const loadPage = useCallback(async () => {
    try {
      setIsLoading(true);
      setErrorMessage("");

      const ticketResponse = await getTicketById(
        ticketId,
        token
      );

      const loadedTicket = normalizeTicket(
        ticketResponse?.data
      );
      setTicket(loadedTicket);
      setMetrics(ticketResponse?.metrics || {});
      setPermissions(ticketResponse?.permissions || {});
      setClassification({
        categoryId:
          loadedTicket?.categoryId ||
          loadedTicket?.category?.id ||
          "",
        priorityId:
          loadedTicket?.priorityId ||
          loadedTicket?.priority?.id ||
          "",
      });
      setAssignment({
        assignedUserId: loadedTicket?.assignedUserId || "",
        reason: "",
      });

      const [commentResponse, attachmentResponse] =
        await Promise.all([
          getTicketComments(ticketId, token),
          getTicketAttachments(ticketId, token),
        ]);

      setComments(arrayFrom(commentResponse));
      setAttachments(arrayFrom(attachmentResponse));
    } catch (error) {
      setErrorMessage(
        errorText(error, "Unable to load this ticket.")
      );
    } finally {
      setIsLoading(false);
    }
  }, [ticketId, token]);

  useEffect(() => {
    loadPage();
  }, [loadPage]);

  useEffect(() => {
    async function loadLookups() {
      try {
        const requests = [
          getCategories(token),
          getPriorities(token),
        ];

        if (["Admin", "Manager"].includes(role)) {
          requests.push(getSupportAgents(token));
        }

        const responses = await Promise.all(requests);
        setCategories(arrayFrom(responses[0]));
        setPriorities(arrayFrom(responses[1]));

        if (responses[2]) {
          setAgents(arrayFrom(responses[2]));
        }
      } catch (error) {
        setErrorMessage(
          errorText(error, "Unable to load ticket options.")
        );
      }
    }

    loadLookups();
  }, [role, token]);

  const timeline = useMemo(() => {
    return [...(ticket?.activityLogs || [])].sort(
      (left, right) =>
        new Date(left.createdAt) - new Date(right.createdAt)
    );
  }, [ticket]);

  async function perform(action, successText) {
    try {
      setIsWorking(true);
      setErrorMessage("");
      await action();
      setSuccessMessage(successText);
      await loadPage();
    } catch (error) {
      setErrorMessage(errorText(error, "Action failed."));
    } finally {
      setIsWorking(false);
    }
  }

  async function handleClassification(event) {
    event.preventDefault();

    await perform(
      () =>
        updateTicket(
          ticketId,
          classification,
          token
        ),
      "Ticket classification updated."
    );
  }

  async function handleAssignment(event) {
    event.preventDefault();

    const isReassignment =
      ticket?.assignedUserId &&
      Number(ticket.assignedUserId) !==
        Number(assignment.assignedUserId);

    if (isReassignment && !assignment.reason.trim()) {
      setErrorMessage(
        "A reason is required when changing the assigned Agent."
      );
      return;
    }

    await perform(
      () => assignTicket(ticketId, assignment, token),
      isReassignment
        ? "Ticket reassigned successfully."
        : "Ticket assigned successfully."
    );
  }

  async function handleAgentRequest(event) {
    event.preventDefault();

    try {
      setIsWorking(true);
      setErrorMessage("");
      setSuccessMessage("");

      await submitAgentRequest(
        ticketId,
        agentRequestMessage,
        token
      );

      setAgentRequestMessage("");
      setAgentRequestSubmitted(true);
      setSuccessMessage(
        "Ticket request submitted successfully. An Admin must approve it."
      );
    } catch (error) {
      setErrorMessage(
        errorText(
          error,
          "Unable to submit the ticket request."
        )
      );
    } finally {
      setIsWorking(false);
    }
  }

  async function handleWorkflow(event) {
    event.preventDefault();

    const note = workflowNote.trim();

    if (
      ["resolve", "escalate", "cancel"].includes(
        workflowAction
      ) &&
      !note
    ) {
      setErrorMessage("A reason or note is required.");
      return;
    }

    const actions = {
      pause: () => pauseTicket(ticketId, note, token),
      resolve: () => resolveTicket(ticketId, note, token),
      escalate: () => escalateTicket(ticketId, note, token),
      cancel: () => cancelTicket(ticketId, note, token),
      close: () => closeTicket(ticketId, note, token),
    };

    if (!actions[workflowAction]) return;

    await perform(
      actions[workflowAction],
      `Ticket ${workflowAction} action completed.`
    );

    setWorkflowAction("");
    setWorkflowNote("");
  }

  async function handleComment(event) {
    event.preventDefault();

    if (!commentText.trim()) return;

    try {
      setIsWorking(true);
      setErrorMessage("");

      const response = await addTicketComment(
        ticketId,
        {
          comment: commentText,
          isInternal,
          parentCommentId: replyTo?.id || null,
        },
        token
      );

      const createdComment = response?.data;

      if (commentFile && createdComment?.id) {
        await uploadTicketAttachment(
          ticketId,
          commentFile,
          token,
          createdComment.id
        );
      }

      setSuccessMessage(
        replyTo ? "Reply added." : "Comment added."
      );
      setCommentText("");
      setIsInternal(false);
      setReplyTo(null);
      setCommentFile(null);
      setCommentFileInputKey((current) => current + 1);
      await loadPage();
    } catch (error) {
      setErrorMessage(
        errorText(error, "Unable to add the comment.")
      );
    } finally {
      setIsWorking(false);
    }
  }

  function canDeleteCommentAttachment(attachment) {
    return (
      !isTerminal &&
      (["Admin", "Manager"].includes(role) ||
        (canUpload &&
          Number(attachment.uploadedByUserId) ===
            Number(user?.id)))
    );
  }

  function downloadCommentAttachment(attachment) {
    downloadTicketAttachment(
      ticketId,
      attachment,
      token
    ).catch((error) =>
      setErrorMessage(
        errorText(error, "Download failed.")
      )
    );
  }

  async function deleteCommentAttachment(attachment) {
    await perform(
      () =>
        deleteTicketAttachment(
          ticketId,
          attachment.id,
          token
        ),
      "Comment attachment deleted."
    );
  }

  async function handleUpload(event) {
    event.preventDefault();

    if (!selectedFile) {
      setErrorMessage("Select a file first.");
      return;
    }

    await perform(
      () =>
        uploadTicketAttachment(
          ticketId,
          selectedFile,
          token
        ),
      "Attachment uploaded."
    );

    setSelectedFile(null);
    event.target.reset();
  }

  async function handleDeleteTicket() {
    if (!window.confirm("Delete this ticket permanently?")) {
      return;
    }

    try {
      setIsWorking(true);
      await deleteTicket(ticketId, token);
      navigate("/tickets", { replace: true });
    } catch (error) {
      setErrorMessage(errorText(error, "Unable to delete ticket."));
      setIsWorking(false);
    }
  }

  if (isLoading && !ticket) {
    return <div className="ticket-loading">Loading ticket…</div>;
  }

  if (!ticket) {
    return (
      <div className="ticket-loading">
        <div>
          <h2>Unable to open ticket</h2>
          <p>{errorMessage}</p>
          <button onClick={() => navigate("/tickets")}>
            Back to tickets
          </button>
        </div>
      </div>
    );
  }

  const workflowOptions = [];

  if (
    isCurrentAgent &&
    statusName === "InProgress"
  ) {
    if (activeSession) {
      workflowOptions.push(["pause", "Pause work"]);
    }
    workflowOptions.push(["resolve", "Resolve"]);
    workflowOptions.push(["escalate", "Escalate"]);
  }

  if (
    (role === "Admin" || role === "Manager") &&
    !["Resolved", "Closed", "Cancelled"].includes(statusName)
  ) {
    workflowOptions.push(["cancel", "Cancel ticket"]);
  }

  if (
    role === "User" &&
    isOwner &&
    statusName === "Open"
  ) {
    workflowOptions.push(["cancel", "Cancel ticket"]);
  }

  if (
    ["Admin", "Manager"].includes(role) &&
    statusName === "Resolved"
  ) {
    workflowOptions.push(["close", "Close ticket"]);
  }

  return (
    <main className="ticket-details-page">
      <div className="ticket-details-shell">
        <div className="ticket-details-toolbar">
          <button
            className="link-button"
            onClick={() => navigate("/tickets")}
          >
            ← Back to tickets
          </button>
          <span
            className={`status-pill status-${statusName.toLowerCase()}`}
          >
            {formatStatus(statusName)}
          </span>
        </div>

        {errorMessage && (
          <div className="alert alert-error">{errorMessage}</div>
        )}
        {successMessage && (
          <div className="alert alert-success">
            {successMessage}
          </div>
        )}

        {role === "SupportAgent" &&
          permissions?.isReadOnlyAgent && (
            <div className="alert alert-info">
              You can review this ticket, but only the currently
              assigned Agent can work on it.
            </div>
          )}

        <section className="ticket-hero card">
          <div>
            <span className="ticket-number">
              {ticket.ticketNumber}
            </span>
            <h1>{ticket.subject}</h1>
            <p className="ticket-description">
              {ticket.description}
            </p>
            
          </div>

          <dl className="ticket-facts">
            <Fact
              label="Created by"
              value={personName(ticket.user)}
            />
            <Fact
              label="Assigned to"
              value={personName(ticket.assignedUser)}
            />
            <Fact
              label="Category"
              value={ticket.category?.categoryName || "—"}
            />
            <Fact
              label="Priority"
              value={ticket.priority?.priorityName || "—"}
            />
            <Fact
              label="Created"
              value={formatDate(ticket.createdAt)}
            />
            <Fact
              label="Due"
              value={formatDate(ticket.dueAt)}
            />
          </dl>
        </section>

        <section className="metrics-grid">
          <Metric
            label="Calendar duration"
            value={formatDuration(
              metrics.calendarDurationSeconds
            )}
          />
          <Metric
            label="Effective work"
            value={formatDuration(
              metrics.effectiveWorkSeconds
            )}
          />
          <Metric
            label="Agents involved"
            value={metrics.agentsInvolved ?? 0}
          />
          <Metric
            label="Reassignments"
            value={metrics.reassignmentCount ?? 0}
          />
        </section>

        <div className="ticket-columns">
          <div className="ticket-main-column">
            {canManageAssignment && !isTerminal && (
              <section className="card section-card">
                <h2>Assignment</h2>
                <form
                  className="stack-form"
                  onSubmit={handleAssignment}
                >
                  <label>
                    Support Agent
                    <select
                      value={assignment.assignedUserId}
                      onChange={(event) =>
                        setAssignment((current) => ({
                          ...current,
                          assignedUserId: event.target.value,
                        }))
                      }
                      required
                    >
                      <option value="">Choose an Agent</option>
                      {agents.map((agent) => (
                        <option key={agent.id} value={agent.id}>
                          {personName(agent)}
                        </option>
                      ))}
                    </select>
                  </label>

                  {ticket.assignedUserId && (
                    <label>
                      Reassignment reason
                      <textarea
                        value={assignment.reason}
                        onChange={(event) =>
                          setAssignment((current) => ({
                            ...current,
                            reason: event.target.value,
                          }))
                        }
                        rows="3"
                        placeholder="Required when changing the Agent"
                      />
                    </label>
                  )}

                  <button
                    className="primary-button"
                    disabled={isWorking}
                  >
                    {ticket.assignedUserId
                      ? "Reassign ticket"
                      : "Assign ticket"}
                  </button>
                </form>
              </section>
            )}

            {isCurrentAgent && statusName === "Assigned" && (
              <section className="card action-card">
                <div>
                  <h2>Ready to begin?</h2>
                  <p>
                    Starting opens an effective-work timer for this
                    ticket.
                  </p>
                </div>
                <button
                  className="primary-button"
                  disabled={isWorking}
                  onClick={() =>
                    perform(
                      () => startTicket(ticketId, token),
                      "Work timer started."
                    )
                  }
                >
                  Start work
                </button>
              </section>
            )}

            {isCurrentAgent &&
              statusName === "InProgress" &&
              !activeSession && (
                <section className="card action-card">
                  <div>
                    <h2>Work is paused</h2>
                    <p>Resume to start a new timed session.</p>
                  </div>
                  <button
                    className="primary-button"
                    disabled={isWorking}
                    onClick={() =>
                      perform(
                        () => resumeTicket(ticketId, token),
                        "Work timer resumed."
                      )
                    }
                  >
                    Resume work
                  </button>
                </section>
              )}

            {workflowOptions.length > 0 && (
              <section className="card section-card">
                <h2>Workflow action</h2>
                <form
                  className="stack-form"
                  onSubmit={handleWorkflow}
                >
                  <label>
                    Action
                    <select
                      value={workflowAction}
                      onChange={(event) => {
                        setWorkflowAction(event.target.value);
                        setWorkflowNote("");
                      }}
                      required
                    >
                      <option value="">Choose an action</option>
                      {workflowOptions.map(([value, label]) => (
                        <option key={value} value={value}>
                          {label}
                        </option>
                      ))}
                    </select>
                  </label>

                  {workflowAction && (
                    <label>
                      {workflowAction === "resolve"
                        ? "Resolution note"
                        : workflowAction === "close"
                          ? "Closing note (optional)"
                          : "Reason"}
                      <textarea
                        value={workflowNote}
                        onChange={(event) =>
                          setWorkflowNote(event.target.value)
                        }
                        rows="4"
                        required={
                          workflowAction !== "pause" &&
                          workflowAction !== "close"
                        }
                      />
                    </label>
                  )}

                  <button
                    className="primary-button"
                    disabled={isWorking || !workflowAction}
                  >
                    Confirm action
                  </button>
                </form>
              </section>
            )}

            <section className="card section-card">
              <h2>Discussion</h2>

              <div className="comment-list">
                {comments.length === 0 ? (
                  <p className="muted">No comments yet.</p>
                ) : (
                  comments.map((comment) => (
                    <Comment
                      key={comment.id}
                      comment={comment}
                      canReply={canComment}
                      onReply={setReplyTo}
                      onDownloadAttachment={
                        downloadCommentAttachment
                      }
                      onDeleteAttachment={
                        deleteCommentAttachment
                      }
                      canDeleteAttachment={
                        canDeleteCommentAttachment
                      }
                    />
                  ))
                )}
              </div>

              {canComment && (
                <form
                  className="comment-form"
                  onSubmit={handleComment}
                >
                  {replyTo && (
                    <div className="reply-banner">
                      Replying to {personName(replyTo.user)}
                      <button
                        type="button"
                        onClick={() => setReplyTo(null)}
                      >
                        ×
                      </button>
                    </div>
                  )}

                  <textarea
                    value={commentText}
                    onChange={(event) =>
                      setCommentText(event.target.value)
                    }
                    rows="4"
                    placeholder={
                      replyTo
                        ? "Write a reply…"
                        : "Add a comment…"
                    }
                    required
                  />

                  <label className="comment-file-field">
                    Optional attachment
                    <input
                      key={commentFileInputKey}
                      type="file"
                      accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt"
                      onChange={(event) =>
                        setCommentFile(
                          event.target.files?.[0] || null
                        )
                      }
                    />
                    <small>
                      PDF, DOC, DOCX, PNG, JPG, or TXT; maximum 10 MB.
                    </small>
                  </label>

                  <div className="form-row">
                    {canUseInternal && !replyTo && (
                      <label className="checkbox-label">
                        <input
                          type="checkbox"
                          checked={isInternal}
                          onChange={(event) =>
                            setIsInternal(event.target.checked)
                          }
                        />
                        Internal note
                      </label>
                    )}

                    <button
                      className="primary-button"
                      disabled={isWorking}
                    >
                      {replyTo ? "Send reply" : "Add comment"}
                    </button>
                  </div>
                </form>
              )}
            </section>

            <section className="card section-card">
              <h2>Attachments</h2>

              {attachments.length === 0 ? (
                <p className="muted">No attachments.</p>
              ) : (
                <div className="attachment-list">
                  {attachments.map((attachment) => {
                    const canDeleteAttachment =
                      !isTerminal &&
                      (role === "Admin" ||
                        (canUpload &&
                          Number(attachment.uploadedByUserId) ===
                            Number(user?.id)));

                    return (
                      <div
                        className="attachment-row"
                        key={attachment.id}
                      >
                        <div>
                          <strong>{attachment.fileName}</strong>
                          <span>
                            {Math.ceil(
                              Number(attachment.fileSize || 0) /
                                1024
                            )}{" "}
                            KB
                          </span>
                        </div>
                        <div>
                          <button
                            className="secondary-button"
                            onClick={() =>
                              downloadTicketAttachment(
                                ticketId,
                                attachment,
                                token
                              ).catch((error) =>
                                setErrorMessage(
                                  errorText(
                                    error,
                                    "Download failed."
                                  )
                                )
                              )
                            }
                          >
                            Download
                          </button>
                          {canDeleteAttachment && (
                            <button
                              className="danger-link"
                              onClick={() =>
                                perform(
                                  () =>
                                    deleteTicketAttachment(
                                      ticketId,
                                      attachment.id,
                                      token
                                    ),
                                  "Attachment deleted."
                                )
                              }
                            >
                              Delete
                            </button>
                          )}
                        </div>
                      </div>
                    );
                  })}
                </div>
              )}

              {canUpload && (
                <form
                  className="upload-form"
                  onSubmit={handleUpload}
                >
                  <input
                    type="file"
                    accept=".pdf,.doc,.docx,.png,.jpg,.jpeg,.txt"
                    onChange={(event) =>
                      setSelectedFile(event.target.files?.[0] || null)
                    }
                    required
                  />
                  <button
                    className="secondary-button"
                    disabled={isWorking}
                  >
                    Upload
                  </button>
                </form>
              )}
            </section>
          </div>

          <aside className="ticket-side-column">
            {canRequestTicket && (
              <section className="card section-card">
                <h2>Request this ticket</h2>

                <p className="muted">
                  Ask an Admin for permission to work on this
                  available ticket.
                </p>

                <form
                  className="stack-form"
                  onSubmit={handleAgentRequest}
                >
                  <label>
                    Message to Admin
                    <textarea
                      rows="4"
                      maxLength="1000"
                      value={agentRequestMessage}
                      placeholder="Explain why you want to handle this ticket (optional)."
                      onChange={(event) =>
                        setAgentRequestMessage(
                          event.target.value
                        )
                      }
                    />
                  </label>

                  <button
                    type="submit"
                    className="primary-button"
                    disabled={isWorking}
                  >
                    {isWorking
                      ? "Submitting..."
                      : "Request Ticket"}
                  </button>
                </form>
              </section>
            )}

            {role === "SupportAgent" &&
              (agentRequestSubmitted ||
                (ticket?.agent_request_status === "requested" &&
                  Number(ticket?.agent_requester_id) ===
                    Number(user?.id))) && (
                <section className="card section-card">
                  <h2>Request pending</h2>
                  <p className="muted">
                    Your request was submitted. You will be
                    notified when an Admin accepts or rejects it.
                  </p>
                </section>
              )}

            {canEditClassification && (
              <section className="card section-card">
                <h2>Classification</h2>
                <p className="muted">
                  Subject and description cannot be edited.
                </p>
                <form
                  className="stack-form"
                  onSubmit={handleClassification}
                >
                  <label>
                    Category
                    <select
                      value={classification.categoryId}
                      onChange={(event) =>
                        setClassification((current) => ({
                          ...current,
                          categoryId: event.target.value,
                        }))
                      }
                      required
                    >
                      {categories.map((category) => (
                        <option
                          key={category.id}
                          value={category.id}
                        >
                          {category.categoryName}
                        </option>
                      ))}
                    </select>
                  </label>
                  <label>
                    Priority
                    <select
                      value={classification.priorityId}
                      onChange={(event) =>
                        setClassification((current) => ({
                          ...current,
                          priorityId: event.target.value,
                        }))
                      }
                      required
                    >
                      {priorities.map((priority) => (
                        <option
                          key={priority.id}
                          value={priority.id}
                        >
                          {priority.priorityName}
                        </option>
                      ))}
                    </select>
                  </label>
                  <button
                    className="secondary-button"
                    disabled={isWorking}
                  >
                    Update classification
                  </button>
                </form>
              </section>
            )}

            <section className="card section-card">
              <h2>Assignment history</h2>
              {(ticket.assignments || []).length === 0 ? (
                <p className="muted">Not assigned yet.</p>
              ) : (
                <div className="compact-timeline">
                  {ticket.assignments.map((item) => (
                    <div key={item.id}>
                      <strong>
                        {personName(item.assignedUser)}
                      </strong>
                      <span>{formatDate(item.assignedAt)}</span>
                      <small>
                        By {personName(item.assignedByUser)}
                        {item.reason ? ` — ${item.reason}` : ""}
                      </small>
                    </div>
                  ))}
                </div>
              )}
            </section>

            <section className="card section-card">
              <h2>Activity timeline</h2>
              {timeline.length === 0 ? (
                <p className="muted">No activity recorded.</p>
              ) : (
                <div className="compact-timeline">
                  {timeline.map((item) => (
                    <div key={item.id}>
                      <strong>
                        {item.action
                          ?.replaceAll("_", " ")
                          .replace(/\b\w/g, (letter) =>
                            letter.toUpperCase()
                          )}
                      </strong>
                      <span>{formatDate(item.createdAt)}</span>
                      <small>{item.description}</small>
                    </div>
                  ))}
                </div>
              )}
            </section>

            {canDelete && (
              <section className="card danger-zone">
                <h2>Danger zone</h2>
                <p>Deletion permanently removes this ticket.</p>
                <button
                  className="danger-button"
                  disabled={isWorking}
                  onClick={handleDeleteTicket}
                >
                  Delete ticket
                </button>
              </section>
            )}
          </aside>
        </div>
      </div>
    </main>
  );
}

function Fact({ label, value }) {
  return (
    <div>
      <dt>{label}</dt>
      <dd>{value}</dd>
    </div>
  );
}

function Metric({ label, value }) {
  return (
    <div className="metric-card">
      <span>{label}</span>
      <strong>{value}</strong>
    </div>
  );
}

function CommentAttachments({
  attachments = [],
  onDownload,
  onDelete,
  canDelete,
}) {
  if (!attachments.length) return null;

  return (
    <div className="comment-attachment-list">
      {attachments.map((attachment) => (
        <div
          className="comment-attachment"
          key={attachment.id}
        >
          <div>
            <strong>{attachment.fileName}</strong>
            <small>
              {Math.ceil(
                Number(attachment.fileSize || 0) / 1024
              )}{" "}
              KB
            </small>
          </div>

          <div className="comment-attachment-actions">
            <button
              type="button"
              className="secondary-button"
              onClick={() => onDownload(attachment)}
            >
              Download
            </button>

            {canDelete(attachment) && (
              <button
                type="button"
                className="danger-link"
                onClick={() => onDelete(attachment)}
              >
                Delete
              </button>
            )}
          </div>
        </div>
      ))}
    </div>
  );
}

function Comment({
  comment,
  canReply,
  onReply,
  onDownloadAttachment,
  onDeleteAttachment,
  canDeleteAttachment,
}) {
  const internal =
    comment.isInternal ?? comment.is_internal ?? false;

  return (
    <article
      className={`comment ${
        internal ? "comment-internal" : ""
      }`}
    >
      <div className="comment-header">
        <div>
          <strong>{personName(comment.user)}</strong>
          {internal && <span>Internal note</span>}
        </div>
        <time>{formatDate(comment.createdAt)}</time>
      </div>
      <p>{comment.comment}</p>
      <CommentAttachments
        attachments={comment.attachments || []}
        onDownload={onDownloadAttachment}
        onDelete={onDeleteAttachment}
        canDelete={canDeleteAttachment}
      />
      {canReply && (
        <button
          className="link-button"
          onClick={() => onReply(comment)}
        >
          Reply
        </button>
      )}

      {(comment.replies || []).map((reply) => (
        <div className="comment-reply" key={reply.id}>
          <div className="comment-header">
            <strong>{personName(reply.user)}</strong>
            <time>{formatDate(reply.createdAt)}</time>
          </div>
          <p>{reply.comment}</p>
          <CommentAttachments
            attachments={reply.attachments || []}
            onDownload={onDownloadAttachment}
            onDelete={onDeleteAttachment}
            canDelete={canDeleteAttachment}
          />
        </div>
      ))}
    </article>
  );
}

export default TicketDetailsPage;