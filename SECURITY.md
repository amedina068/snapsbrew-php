# Security policy

## Supported versions

| Version | Supported |
|---|---|
| 0.1.x | Yes |

## Report a security problem

Report security problems in private. Do not open a public issue, a discussion or a pull request.

1. Open the **Security** tab of the repository on GitHub.
2. Click **Report a vulnerability**.
3. Write the problem, the affected versions and the steps to reproduce it.

GitHub sends the report to the maintainers only. The maintainers reply in the advisory.

## Secrets

The SDK uses two secrets: the API token and the signing key.

- Keep the two secrets on the server.
- Never put the signing key in browser code or in a mobile app.
- If a secret is exposed, revoke it on your Snapsbrew instance. Then create a new one.
