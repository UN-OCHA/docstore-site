# Test webhooks endpoints

## POST /webhooks

Register a [webhook](https://webhook.site/#!/596df11a-21f8-4790-bb90-f79ba4ef9df6/6bbb526f-3610-459d-8c46-370aa8e9f695/1).

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My webhook",
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `201`
* Content-Type: "application/json"
* Data.message: "Webhook created"
* Data.machine_name: /^[0-9a-z_]+$/ // Machine_name {machine_name}

## POST /webhooks

Register again.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My webhook",
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Webhook already exists"

## POST /webhooks

Register without payload url.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "label": "My webhook"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Payload URL is required"

## POST /webhooks

Register without label.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

```json
{
  "payload_url": "https://webhook.site/596df11a-21f8-4790-bb90-f79ba4ef9df6"
}
```

===

* Status: `400`
* Content-Type: "application/json"
* Data.message: "Label is required"

## GET /webhooks

Get web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

```json
[]
```

* Status: `200`
* Content-Type: "application/json"

## GET /webhooks

Get web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

* Status: `200`
* Content-Type: "application/json"
* Data[0].machine_name: "{machine_name}"


## DELETE /webhooks/{machine_name}

Delete web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: dcba

===

```json
{
  "message": "Webhook is not owned by you"
}
```

* Status: `403`
* Content-Type: "application/json"

## DELETE /webhooks/{machine_name}

Delete web hooks.

* Content-Type: "application/json"
* Accept: "application/json"
* API-KEY: abcd

===

```json
{
  "message": "Webhook deleted"
}
```

* Status: `200`
* Content-Type: "application/json"
