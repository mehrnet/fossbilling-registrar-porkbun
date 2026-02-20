# FossBilling Porkbun Registrar Adapter

Porkbun domain registrar adapter for FOSSBilling using the Porkbun JSON API v3.

Package: `mehrnet/fossbilling-registrar-porkbun`
Extension ID (for directory listing): `Porkbun`

## Implemented features

- Domain availability checks
- Domain registration
- Domain nameserver updates
- Domain detail sync (registration date, expiry date, lock/privacy flags, nameservers)

## Not implemented (Porkbun API v3 limitation)

- Transfer availability and transfer submit
- Manual renewal action
- EPP/auth-code retrieval
- Contact updates
- Domain delete action
- Lock/privacy toggle actions

These methods throw clear `Registrar_Exception` messages so behavior is explicit in FossBilling.

## Installation

### Automatically from the extension directory (recommended)
1. Log in to your FOSSBilling admin panel.
2. Go to the Extensions page.
3. Click `Install` for `Porkbun`.
4. Go to `System > Domain registration`.
5. In `New domain registrar`, enable `Porkbun`, then configure:
   - `API Key`
   - `Secret API Key`
   - Optional `API Base URL` (defaults to `https://api.porkbun.com/api/json/v3`)

### Manual `.zip` installation
1. Download the latest release archive from this repository.
2. Extract the archive into your FOSSBilling installation root so this file exists:
   - `library/Registrar/Adapter/Porkbun.php`
3. Log in to FOSSBilling admin panel.
4. Go to `System > Domain registration`.
5. In `New domain registrar`, enable `Porkbun` and enter your API credentials.

## Extension directory listing notes

- Release source should be a stable downloadable archive URL (GitHub release asset or tag zip).
- Use extension type `domain-registrar`.
- Keep versioned releases so the directory can track:
  - `tag`
  - `date`
  - `download_url`
  - `changelog_url`
  - `min_fossbilling_version`

## Notes

- Porkbun registration API expects the `cost` in pennies and only supports the registry minimum duration for each TLD.
- Premium domains are rejected by this adapter.
