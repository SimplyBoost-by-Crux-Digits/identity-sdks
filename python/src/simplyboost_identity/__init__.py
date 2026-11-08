"""Verified user identity for the SimplyBoost chat widget.

Your backend signs the logged-in user's email with the chatbot's identity
secret; the widget passes ``{email, name, ts, user_hash}`` to SimplyBoost,
which recomputes the signature and marks the chat as that user. The secret
stays on your server and must never reach the browser.

The rule SimplyBoost verifies::

    user_hash = hex(HMAC_SHA256(secret, lower(strip(email)) + ":" + ts))

``ts`` is a Unix timestamp in seconds. A signature is accepted for
:data:`TTL_SECONDS` after ``ts`` (with :data:`SKEW_SECONDS` of clock skew), so
sign on every page render, never once.

Guide: https://simplyboost.io/docs/verified-visitors
"""
from __future__ import annotations

import hashlib
import hmac
import html
import json
import time
from typing import Any, Dict, Mapping, Optional, TypedDict

__version__ = "1.0.0"
__all__ = [
    "DEFAULT_WIDGET_URL",
    "IdentityError",
    "SKEW_SECONDS",
    "TTL_SECONDS",
    "compute_user_hash",
    "identity",
    "normalize_email",
    "visitor",
    "widget_config",
    "widget_snippet",
]

#: How long SimplyBoost accepts a signature after its ``ts``.
TTL_SECONDS = 600
#: How far ``ts`` may be ahead of SimplyBoost's clock.
SKEW_SECONDS = 60
#: The widget script every snippet loads.
DEFAULT_WIDGET_URL = "https://get.simplyboost.io/widget.js"

_MAX_EMAIL_LENGTH = 320


class IdentityError(ValueError):
    """Raised for input that SimplyBoost would never accept (no secret, no
    email, a value that is not an email address, a negative timestamp)."""


class Signature(TypedDict):
    ts: int
    user_hash: str


class _SignedVisitor(TypedDict):
    email: str
    ts: int
    user_hash: str


class Visitor(_SignedVisitor, total=False):
    name: str


def normalize_email(email: str) -> str:
    """The email exactly as SimplyBoost signs and stores it: trimmed and
    lowercased."""
    if not isinstance(email, str):
        raise IdentityError("email must be a string")
    normalized = email.strip().lower()
    if not normalized:
        raise IdentityError("email is required")
    if "@" not in normalized or len(normalized) > _MAX_EMAIL_LENGTH:
        raise IdentityError("email is not an email address")
    return normalized


def _timestamp(ts: Optional[int]) -> int:
    if ts is None:
        return int(time.time())
    if isinstance(ts, bool) or not isinstance(ts, int):
        raise IdentityError("ts must be a whole number of seconds")
    if ts < 0:
        raise IdentityError("ts must not be negative")
    return ts


def compute_user_hash(secret: str, email: str, ts: int) -> str:
    """The hex HMAC-SHA256 of ``"<normalized email>:<ts>"`` with ``secret``."""
    if not isinstance(secret, str) or not secret:
        raise IdentityError("secret is required")
    payload = f"{normalize_email(email)}:{_timestamp(ts)}".encode("utf-8")
    return hmac.new(secret.encode("utf-8"), payload, hashlib.sha256).hexdigest()


def identity(secret: str, email: str, ts: Optional[int] = None) -> Signature:
    """Return ``{"ts", "user_hash"}`` for a logged-in user (``ts`` defaults to
    now)."""
    stamp = _timestamp(ts)
    return {"ts": stamp, "user_hash": compute_user_hash(secret, email, stamp)}


def visitor(
    secret: str,
    email: str,
    name: Optional[str] = None,
    ts: Optional[int] = None,
) -> Visitor:
    """The four values the widget needs for a logged-in user:
    ``{"email", "name", "ts", "user_hash"}`` with the email normalized as
    signed. Return it from your refresh endpoint as JSON, or pass it to
    :func:`widget_snippet`."""
    signature = identity(secret, email, ts)
    result: Visitor = {
        "email": normalize_email(email),
        "ts": signature["ts"],
        "user_hash": signature["user_hash"],
    }
    if name:
        result["name"] = str(name).strip()
    return result


def widget_config(
    bot_id: str,
    visitor: Optional[Mapping[str, Any]] = None,
    refresh_url: Optional[str] = None,
) -> Dict[str, Any]:
    """The ``window._CHATBOT_CONFIG_`` object. ``refresh_url`` (a path on your
    own site that returns a fresh :func:`visitor`) is only added for a
    signed-in visitor."""
    if not isinstance(bot_id, str) or not bot_id.strip():
        raise IdentityError("bot_id is required")
    config: Dict[str, Any] = {"chat_bot_id": bot_id.strip()}
    if visitor:
        signed = dict(visitor)
        if refresh_url:
            signed["refresh_url"] = refresh_url
        config["visitor"] = signed
    return config


def _script_json(value: Any) -> str:
    """JSON that is safe inside an inline ``<script>`` element."""
    return (
        json.dumps(value, ensure_ascii=True, separators=(",", ":"))
        .replace("<", "\\u003c")
        .replace(">", "\\u003e")
        .replace("&", "\\u0026")
    )


def widget_snippet(
    bot_id: str,
    visitor: Optional[Mapping[str, Any]] = None,
    refresh_url: Optional[str] = None,
    nonce: Optional[str] = None,
    widget_url: str = DEFAULT_WIDGET_URL,
) -> str:
    """The HTML that loads the widget, for the end of ``<body>``. Pass a
    ``nonce`` when your Content-Security-Policy uses one. The result is
    trusted HTML: mark it safe in your template engine, never build it from
    request input other than the signed-in user."""
    config = _script_json(widget_config(bot_id, visitor, refresh_url))
    separator = "&" if "?" in widget_url else "?"
    src = _script_json(widget_url + separator + "v=")
    attr = f' nonce="{html.escape(nonce, quote=True)}"' if nonce else ""
    return (
        f"<script{attr}>window._CHATBOT_CONFIG_ = {config};</script>\n"
        f"<script{attr}>(function(d,s,id){{var js=d.createElement(s);js.id=id;"
        f"js.src={src}+Date.now();d.body.appendChild(js);}})"
        f"(document,'script','simplyboost-classic-script');</script>"
    )
