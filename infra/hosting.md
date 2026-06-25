# Hosting Notes

## Recommendation

Start with Render or DigitalOcean App Platform for simplicity. Keep the architecture compatible with Google Cloud Run later.

## Render

Pros:

- Simple web service deployment.
- Managed PostgreSQL available.
- Redis available through add-ons or compatible services.
- Easy environment variables.
- Good fit for an early Laravel SaaS.

Cons:

- Worker and queue setup must be configured carefully.
- Advanced networking and secret management are simpler than full cloud platforms.

## DigitalOcean App Platform

Pros:

- Simple app hosting.
- Managed PostgreSQL and Redis options.
- Clear pricing and operational model.
- Good fit for small SaaS deployments.

Cons:

- Less flexible than full Kubernetes or Google Cloud setups.
- Queue workers and scheduled jobs need explicit configuration.

## Google Cloud Run

Pros:

- Scales well with containers.
- Strong secret management and observability options.
- Good long-term fit for stateless Laravel web and worker containers.

Cons:

- More setup complexity.
- Requires stronger container, queue, scheduler, and networking discipline.
- Better after the platform architecture is proven.

## Production Requirements Later

- Managed PostgreSQL with backups.
- Redis or durable queue backend.
- Separate web, worker, and scheduler processes.
- HTTPS only.
- Per-environment secrets.
- Database backup/restore test.
- Monitoring and alerting.

