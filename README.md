# FossBilling Porkbun Registrar Adapter

Marketplace-ready FossBilling extension that adds a `Porkbun` registrar adapter using the Porkbun JSON API v3.

Package: `mehrnet/fossbilling-registrar-porkbun`

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

1. Add/publish this package in your FossBilling marketplace source.
2. Install it as an extension package (`type: fossbilling-extension`).
3. In FossBilling, configure the `Porkbun` registrar with:
   - `API Key`
   - `Secret API Key`
   - Optional `API Base URL` (defaults to `https://api.porkbun.com/api/json/v3`)

## Notes

- Porkbun registration API expects the `cost` in pennies and only supports the registry minimum duration for each TLD.
- Premium domains are rejected by this adapter.
