namespace Enterprise.Sso.Domain.Audit;

public sealed class AuditEvent
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string EventType { get; set; }
    public required string Category { get; set; }
    public required string Severity { get; set; }
    public Guid? UserId { get; set; }
    public Guid? ActorUserId { get; set; }
    public Guid? TenantId { get; set; }
    public string? ClientId { get; set; }
    public string? SessionId { get; set; }
    public string? CorrelationId { get; set; }
    public string? TraceId { get; set; }
    public string? IpAddressHash { get; set; }
    public string? UserAgentHash { get; set; }
    public string? ResourceType { get; set; }
    public string? ResourceId { get; set; }
    public required string Action { get; set; }
    public required string Result { get; set; }
    public string? FailureReason { get; set; }
    public string? BeforeDataJson { get; set; }
    public string? AfterDataJson { get; set; }
    public DateTimeOffset OccurredAt { get; init; } = DateTimeOffset.UtcNow;
    public string? PreviousHash { get; set; }
    public required string CurrentHash { get; set; }
}
