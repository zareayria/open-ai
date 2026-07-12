namespace Enterprise.Sso.Domain.Identity;

public sealed class ApplicationUser
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string UserName { get; set; }
    public required string NormalizedUserName { get; set; }
    public required string Email { get; set; }
    public required string NormalizedEmail { get; set; }
    public bool EmailConfirmed { get; set; }
    public string? PhoneNumber { get; set; }
    public bool PhoneNumberConfirmed { get; set; }
    public bool IsActive { get; set; } = true;
    public bool IsDeleted { get; set; }
    public bool MustChangePassword { get; set; }
    public DateTimeOffset? PasswordExpiresAt { get; set; }
    public DateTimeOffset? LastLoginAt { get; set; }
    public string? LastLoginIpHash { get; set; }
    public string? LastUserAgentHash { get; set; }
    public string? DisabledReason { get; set; }
    public DateTimeOffset? AnonymizedAt { get; set; }
    public byte[] RowVersion { get; set; } = [];
}
