"""SimplyBoost chat widget with verified identity in a Flask app (Flask-Login).

    pip install flask flask-login simplyboost-identity
    export SIMPLYBOOST_BOT_ID=<your chatbot id>
    export SIMPLYBOOST_IDENTITY_SECRET=<Install -> Identity verification>
    flask --app app run

Your templates print {{ simplyboost_widget }} just before </body>.
"""
import os

from flask import Flask, jsonify, url_for
from flask_login import LoginManager, current_user
from markupsafe import Markup

from simplyboost_identity import visitor, widget_snippet

BOT_ID = os.environ["SIMPLYBOOST_BOT_ID"]
# Read on the server only. The secret must never be rendered into a page.
SECRET = os.environ["SIMPLYBOOST_IDENTITY_SECRET"]

app = Flask(__name__)
app.secret_key = os.environ["FLASK_SECRET_KEY"]
login_manager = LoginManager(app)
# Register your own @login_manager.user_loader and login views as usual.


def _signed_visitor():
    if not current_user.is_authenticated:
        return None
    return visitor(SECRET, current_user.email, name=getattr(current_user, "name", None))


@app.context_processor
def simplyboost_widget():
    snippet = widget_snippet(
        BOT_ID,
        _signed_visitor(),
        refresh_url=url_for("simplyboost_identity"),
    )
    # widget_snippet escapes every value for the <script> element.
    return {"simplyboost_widget": Markup(snippet)}


@app.get("/simplyboost/identity")
def simplyboost_identity():
    """Fresh signature for pages left open longer than the 10-minute window."""
    signed = _signed_visitor()
    response = jsonify(signed or {})
    response.status_code = 200 if signed else 401
    response.headers["Cache-Control"] = "no-store"
    return response
