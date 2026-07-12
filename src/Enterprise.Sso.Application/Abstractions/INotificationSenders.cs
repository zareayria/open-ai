namespace Enterprise.Sso.Application.Abstractions;

public interface IEmailSender
{
    Task SendAsync(EmailMessage message, CancellationToken cancellationToken);
}

public interface ISmsSender
{
    Task SendAsync(SmsMessage message, CancellationToken cancellationToken);
}

public interface INotificationTemplateRenderer
{
    Task<string> RenderAsync(string templateName, object model, CancellationToken cancellationToken);
}

public sealed record EmailMessage(string To, string Subject, string Body, bool IsHtml);
public sealed record SmsMessage(string To, string Body);
