import { Link } from "react-router";

function AuthLayout({ children }) {
  return (
    <main className="auth-page">
      <section className="auth-brand-panel">
        <div className="brand-top">
          <div className="brand-icon">✉</div>
          <span>HelpDesk Pro</span>
        </div>

        <div className="brand-illustration">
          <div className="support-agent">🎧</div>

          <div className="ticket-screen">
            <div className="ticket-row">
              <span className="ticket-dot green" />
              <span className="ticket-line long" />
            </div>

            <div className="ticket-row">
              <span className="ticket-dot orange" />
              <span className="ticket-line medium" />
            </div>

            <div className="ticket-row">
              <span className="ticket-dot red" />
              <span className="ticket-line short" />
            </div>
          </div>
        </div>

        <div className="brand-copy">
          <h1>
            Resolve tickets
            <br />
            faster, together.
          </h1>

          <p>
            A unified workspace for your support team to manage, track, and
            resolve customer issues at scale.
          </p>
        </div>

        <div className="brand-benefits">
          <div className="benefit-card">
            <strong>⚡</strong>
            <span>2× faster resolution</span>
          </div>

          <div className="benefit-card">
            <strong>◷</strong>
            <span>24/7 tracking</span>
          </div>

          <div className="benefit-card">
            <strong>◇</strong>
            <span>SOC 2 compliant</span>
          </div>
        </div>

        <p className="brand-footer">© 2026 HelpDesk Pro. All rights reserved.</p>
      </section>

      <section className="auth-form-panel">
        <div className="auth-content">{children}</div>

        <p className="legal-text">
          By continuing, you agree to our{" "}
          <Link to="/terms">Terms of Service</Link> and{" "}
          <Link to="/privacy">Privacy Policy</Link>.
        </p>
      </section>
    </main>
  );
}

export default AuthLayout;