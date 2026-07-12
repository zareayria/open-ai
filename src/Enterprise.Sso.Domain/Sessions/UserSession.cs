namespace Enterprise.Sso.Domain.Sessions;

public sealed class UserSession
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string SessionId { get; set; }
    public Guid UserId { get; set; }
    public Guid TenantId { get; set; }
    public string? ClientId { get; set; }
    public string? IpAddressHash { get; set; }
    public string? UserAgentHash { get; set; }
    public string? DeviceName { get; set; }
    public string? Browser { get; set; }
    public string? OperatingSystem { get; set; }
    public DateTimeOffset CreatedAt { get; init; } = DateTimeOffset.UtcNow;
    public DateTimeOffset LastActivityAt { get; set; } = DateTimeOffset.UtcNow;
    public DateTimeOffset ExpiresAt { get; set; }
    public DateTimeOffset? RevokedAt { get; set; }
    public string? RevokeReason { get; set; }
    public required string AuthenticationMethod { get; set; }
    public bool MfaSatisfied { get; set; }
    public RiskLevel RiskLevel { get; set; } = RiskLevel.Low;
    public byte[] RowVersion { get; set; } = [];
}

public enum RiskLevel
{
    Low = 1,
    Medium = 2,
    High = 3,
    Critical = 4
}
