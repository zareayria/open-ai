namespace Enterprise.Sso.Application.Security;

public sealed record PasswordPolicy(
    int MinimumLength,
    bool RequireUppercase,
    bool RequireLowercase,
    bool RequireDigit,
    bool RequireNonAlphanumeric,
    int PasswordHistoryCount,
    TimeSpan PasswordLifetime,
    TimeSpan PasswordResetTokenLifetime,
    int AccountLockoutThreshold,
    TimeSpan LockoutDuration);
