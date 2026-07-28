function FormInput({
  id,
  label,
  type = "text",
  value,
  onChange,
  placeholder,
  autoComplete,
  icon,
  error,
  required = false,
}) {
  return (
    <div className="form-group">
      <label htmlFor={id}>
        {label}
        {required && <span className="required-mark"> *</span>}
      </label>

      <div className={`input-wrapper ${error ? "has-error" : ""}`}>
        {icon && <span className="input-icon">{icon}</span>}

        <input
          id={id}
          name={id}
          type={type}
          value={value}
          onChange={onChange}
          placeholder={placeholder}
          autoComplete={autoComplete}
          aria-invalid={Boolean(error)}
          aria-describedby={error ? `${id}-error` : undefined}
        />
      </div>

      {error && (
        <p className="field-error" id={`${id}-error`}>
          {error}
        </p>
      )}
    </div>
  );
}

export default FormInput;