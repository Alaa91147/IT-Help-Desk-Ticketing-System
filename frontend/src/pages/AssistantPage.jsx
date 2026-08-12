import ChatAssistant from "../components/Assistant/ChatAssistant";
import DashboardLayout from "../components/Dashboard/DashboardLayout";

export default function AssistantPage() {
  return (
    <DashboardLayout>
      <div style={styles.page}>
        <header style={styles.header}>
          <span style={styles.eyebrow}>SUPPORT ASSISTANT</span>
          <h1 style={styles.title}>How can I help?</h1>
          <p style={styles.subtitle}>
            Get troubleshooting guidance and solutions from previously
            resolved tickets. No paid AI key is required.
          </p>
        </header>
        <ChatAssistant />
      </div>
    </DashboardLayout>
  );
}

const styles = {
  page: { width: "min(900px, 100%)", margin: "0 auto" },
  header: { marginBottom: 22 },
  eyebrow: { color: "#2563eb", fontSize: 12, fontWeight: 800, letterSpacing: ".1em" },
  title: { margin: "8px 0", color: "#172033", fontSize: "clamp(28px, 4vw, 40px)" },
  subtitle: { maxWidth: 690, margin: 0, color: "#667085", lineHeight: 1.6 },
};