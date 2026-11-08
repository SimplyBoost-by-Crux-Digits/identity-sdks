"""SimplyBoost chat widget with verified identity in a Django project.

1. pip install simplyboost-identity
2. Set the environment variables on the server (never in templates or JS):
       SIMPLYBOOST_BOT_ID=<your chatbot id>
       SIMPLYBOOST_IDENTITY_SECRET=<Install -> Identity verification>
3. settings.py:
       TEMPLATES[0]["OPTIONS"]["context_processors"].append(
           "yourapp.simplyboost_views.simplyboost_widget")
4. urls.py:
       from yourapp.simplyboost_views import identity_refresh
       urlpatterns += [path("simplyboost/identity", identity_refresh, name="simplyboost-identity")]
5. base.html, just before </body>:
       {{ simplyboost_widget }}
"""
import os

from django.http import JsonResponse
from django.urls import reverse
from django.utils.safestring import mark_safe
from django.views.decorators.cache import never_cache
from django.views.decorators.http import require_GET

from simplyboost_identity import visitor, widget_snippet

BOT_ID = os.environ["SIMPLYBOOST_BOT_ID"]
# Read on the server only. The secret must never be rendered into a page.
SECRET = os.environ["SIMPLYBOOST_IDENTITY_SECRET"]


def _signed_visitor(user):
    if not user.is_authenticated or not user.email:
        return None
    return visitor(SECRET, user.email, name=user.get_full_name() or None)


def simplyboost_widget(request):
    """Context processor: {{ simplyboost_widget }} prints the widget snippet,
    signed for the logged-in user and anonymous for guests."""
    snippet = widget_snippet(
        BOT_ID,
        _signed_visitor(request.user),
        refresh_url=reverse("simplyboost-identity"),
        # If you use django-csp: nonce=request.csp_nonce
    )
    # widget_snippet escapes every value for the <script> element.
    return {"simplyboost_widget": mark_safe(snippet)}


@never_cache
@require_GET
def identity_refresh(request):
    """Fresh signature for pages left open longer than the 10-minute window.
    Same origin only; the widget calls it with the session cookie."""
    signed = _signed_visitor(request.user)
    if signed is None:
        return JsonResponse({}, status=401)
    return JsonResponse(signed)
