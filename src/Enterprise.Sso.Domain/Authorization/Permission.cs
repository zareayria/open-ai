namespace Enterprise.Sso.Domain.Authorization;

public sealed class Permission
{
    public Guid Id { get; init; } = Guid.NewGuid();
    public required string Name { get; set; }
    public required string Description { get; set; }
    public bool IsSystemPermission { get; set; } = true;
}

public static class SystemPermissions
{
    public const string UsersView = "Users.View";
    public const string UsersCreate = "Users.Create";
    public const string UsersUpdate = "Users.Update";
    public const string UsersDelete = "Users.Delete";
    public const string RolesView = "Roles.View";
    public const string RolesManage = "Roles.Manage";
    public const string ClientsView = "Clients.View";
    public const string ClientsManage = "Clients.Manage";
    public const string AuditView = "Audit.View";
    public const string SecurityEventsView = "SecurityEvents.View";
    public const string TenantsManage = "Tenants.Manage";
}
