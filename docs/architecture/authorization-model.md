# Authorization Model

Authorization combines RBAC, permission policies, claims, tenant boundaries, and resource ownership. Controllers authorize against policies such as `Permission:Users.View` rather than hard-coded roles. A central handler evaluates direct user permissions, role permissions, group roles, tenant membership, global administrator status, and resource tenant ownership.

Default permissions include `Users.View`, `Users.Create`, `Users.Update`, `Users.Delete`, `Roles.View`, `Roles.Manage`, `Clients.View`, `Clients.Manage`, `Audit.View`, `SecurityEvents.View`, and `Tenants.Manage`.
