function StatCard({
  title,
  value,
  detail,
  tone = "default",
}) {
  return (
    <article className={`stat-card stat-${tone}`}>
      <span>{title}</span>
      <strong>{value}</strong>
      {detail && <small>{detail}</small>}
    </article>
  );
}

export default StatCard;