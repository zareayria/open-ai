namespace Enterprise.Sso.Domain.Keys;

public sealed class SigningKeyMetadata
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string KeyId { get; set; }
    public required string Algorithm { get; set; }
    public required string Thumbprint { get; set; }
    public DateTimeOffset ActivatedAt { get; set; }
    public DateTimeOffset? RetiredAt { get; set; }
    public DateTimeOffset? NotAfter { get; set; }
    public SigningKeyState State { get; set; } = SigningKeyState.Active;
}

public enum SigningKeyState
{
    Pending = 1,
    Active = 2,
    Retired = 3,
    Compromised = 4
}
