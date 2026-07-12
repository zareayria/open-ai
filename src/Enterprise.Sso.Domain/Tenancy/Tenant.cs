namespace Enterprise.Sso.Domain.Tenancy;

public sealed class Tenant
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string Name { get; set; }
    public required string Slug { get; set; }
    public string? Domain { get; set; }
    public TenantStatus Status { get; set; } = TenantStatus.Active;
    public DateTimeOffset CreatedAt { get; init; } = DateTimeOffset.UtcNow;
    public string? Plan { get; set; }
    public string? BrandingJson { get; set; }
    public string? LoginPolicyJson { get; set; }
    public string? PasswordPolicyJson { get; set; }
    public string? MfaPolicyJson { get; set; }
    public string? SessionPolicyJson { get; set; }
    public byte[] RowVersion { get; set; } = [];
}

public enum TenantStatus
{
    Active = 1,
    Suspended = 2,
    Disabled = 3
}
