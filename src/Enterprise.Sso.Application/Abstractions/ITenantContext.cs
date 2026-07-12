namespace Enterprise.Sso.Application.Abstractions;

public interface ITenantContext
{
    Guid? TenantId { get; }
    bool IsGlobalAdministrator { get; }
}
