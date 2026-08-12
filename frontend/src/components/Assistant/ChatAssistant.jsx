import { useState } from "react";

import { assistantChat } from "../../api/assistantApi";
import { useAuth } from "../../context/AuthContext";

const welcome = {
  role: "assistant",
  content:
    "Hello! Describe your IT problem and I’ll suggest troubleshooting steps or find a similar resolved issue.",
  relatedTickets: [],
};

export default function ChatAssistant() {
  const { token } = useAuth();
  const [messages, setMessages] = useState([welcome]);
  const [input, setInput] = useState("");
  const [loading, setLoading] = useState(false);

  async function send(event) {
    event.preventDefault();
    const content = input.trim();
    if (!content || loading) return;

    const userMessage = { role: "user", content };
    const nextMessages = [...messages, userMessage];
    const requestMessages = nextMessages
      .slice(-20)
      .map(({ role, content: text }) => ({ role, content: text }));

    setMessages(nextMessages);
    setInput("");
    setLoading(true);

    try {
      const response = await assistantChat(requestMessages, token);
      const data = response?.data || {};

      setMessages((current) => [
        ...current,
        {
          role: "assistant",
          content: data.message || "I could not generate a response.",
          relatedTickets: Array.isArray(data.relatedTickets)
            ? data.relatedTickets
            : [],
        },
      ]);
    } catch (error) {
      setMessages((current) => [
        ...current,
        {
          role: "assistant",
          content:
            error.message || "The assistant is temporarily unavailable.",
          isError: true,
          relatedTickets: [],
        },
      ]);
    } finally {
      setLoading(false);
    }
  }

  return (
    <section style={styles.card}>
      <div style={styles.messages} aria-live="polite">
        {messages.map((message, index) => (
          <div
            key={`${message.role}-${index}`}
            style={{
              ...styles.row,
              justifyContent:
                message.role === "user" ? "flex-end" : "flex-start",
            }}
          >
            <div
              style={{
                ...styles.bubble,
                ...(message.role === "user"
                  ? styles.userBubble
                  : styles.assistantBubble),
                ...(message.isError ? styles.errorBubble : {}),
              }}
            >
              <p style={styles.text}>{message.content}</p>

              {message.relatedTickets?.length > 0 && (
                <div style={styles.relatedList}>
                  <strong>Related resolved tickets</strong>
                  {message.relatedTickets.map((ticket) => (
                    <article
                      key={ticket.ticketNumber}
                      style={styles.relatedTicket}
                    >
                      <div style={styles.relatedHeading}>
                        <strong>{ticket.ticketNumber}</strong>
                        <span>{ticket.similarity}% similar</span>
                      </div>
                      <p style={styles.relatedSubject}>{ticket.subject}</p>
                      <small>{ticket.category || "Uncategorized"}</small>
                    </article>
                  ))}
                </div>
              )}
            </div>
          </div>
        ))}

        {loading && (
          <div style={styles.row}>
            <div style={{ ...styles.bubble, ...styles.assistantBubble }}>
              Searching solutions…
            </div>
          </div>
        )}
      </div>

      <form style={styles.form} onSubmit={send}>
        <textarea
          value={input}
          onChange={(event) => setInput(event.target.value)}
          onKeyDown={(event) => {
            if (event.key === "Enter" && !event.shiftKey) {
              event.preventDefault();
              event.currentTarget.form?.requestSubmit();
            }
          }}
          placeholder="Example: I cannot connect to the office Wi-Fi"
          maxLength={2000}
          rows={3}
          style={styles.input}
          disabled={loading}
          aria-label="Message to support assistant"
        />
        <button
          type="submit"
          style={styles.button}
          disabled={loading || !input.trim()}
        >
          {loading ? "Searching…" : "Send"}
        </button>
      </form>
    </section>
  );
}

const styles = {
  card: { overflow: "hidden", border: "1px solid #dce4ef", borderRadius: 16, background: "#fff", boxShadow: "0 8px 28px rgba(15,23,42,.06)" },
  messages: { minHeight: 430, maxHeight: "60vh", overflowY: "auto", padding: 24, background: "#f8fafc" },
  row: { display: "flex", marginBottom: 16 },
  bubble: { width: "fit-content", maxWidth: "78%", padding: "13px 16px", borderRadius: 14, lineHeight: 1.55 },
  userBubble: { color: "#fff", background: "#2563eb", borderBottomRightRadius: 4 },
  assistantBubble: { color: "#172033", background: "#fff", border: "1px solid #e2e8f0", borderBottomLeftRadius: 4 },
  errorBubble: { color: "#991b1b", background: "#fef2f2", borderColor: "#fecaca" },
  text: { margin: 0, whiteSpace: "pre-wrap" },
  relatedList: { display: "grid", gap: 9, marginTop: 14, paddingTop: 13, borderTop: "1px solid #e2e8f0" },
  relatedTicket: { padding: 10, borderRadius: 9, background: "#eff6ff" },
  relatedHeading: { display: "flex", justifyContent: "space-between", gap: 12, color: "#1d4ed8", fontSize: 13 },
  relatedSubject: { margin: "6px 0 3px", fontWeight: 600 },
  form: { display: "flex", alignItems: "flex-end", gap: 12, padding: 18, borderTop: "1px solid #e2e8f0" },
  input: { flex: 1, resize: "vertical", minHeight: 48, padding: 12, border: "1px solid #cbd5e1", borderRadius: 10, font: "inherit" },
  button: { minWidth: 100, padding: "13px 20px", border: 0, borderRadius: 10, color: "#fff", background: "#2563eb", fontWeight: 700, cursor: "pointer" },
};