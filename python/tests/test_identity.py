import json
import time
import unittest
from pathlib import Path

import simplyboost_identity as sb

VECTORS = json.loads(
    (Path(__file__).resolve().parents[2] / "test-vectors.json").read_text(encoding="utf-8")
)


class SharedVectorsTest(unittest.TestCase):
    def test_every_vector_matches_the_server(self):
        for vector in VECTORS["vectors"]:
            with self.subTest(vector["name"]):
                self.assertEqual(
                    sb.compute_user_hash(vector["secret"], vector["email"], vector["ts"]),
                    vector["user_hash"],
                )
                self.assertEqual(sb.normalize_email(vector["email"]), vector["normalized_email"])

    def test_window_constants_match_the_server(self):
        self.assertEqual(sb.TTL_SECONDS, VECTORS["ttl_seconds"])
        self.assertEqual(sb.SKEW_SECONDS, VECTORS["skew_seconds"])


class IdentityTest(unittest.TestCase):
    SECRET = "test-secret"

    def test_identity_defaults_to_now(self):
        before = int(time.time())
        result = sb.identity(self.SECRET, "a@example.com")
        self.assertGreaterEqual(result["ts"], before)
        self.assertLessEqual(result["ts"], int(time.time()))
        self.assertRegex(result["user_hash"], r"^[0-9a-f]{64}$")

    def test_visitor_returns_the_normalized_email_it_signed(self):
        result = sb.visitor(self.SECRET, "  Jane@Example.COM ", name=" Jane ", ts=1792768282)
        self.assertEqual(result["email"], "jane@example.com")
        self.assertEqual(result["name"], "Jane")
        self.assertEqual(result["ts"], 1792768282)
        self.assertEqual(
            result["user_hash"], sb.compute_user_hash(self.SECRET, "jane@example.com", 1792768282)
        )

    def test_visitor_without_name_leaves_it_out(self):
        self.assertNotIn("name", sb.visitor(self.SECRET, "a@example.com", ts=1))

    def test_refuses_input_the_server_would_refuse(self):
        for args in (
            ("", "a@example.com", 1),
            (self.SECRET, "", 1),
            (self.SECRET, "   ", 1),
            (self.SECRET, "not-an-email", 1),
            (self.SECRET, "a@" + "b" * 400, 1),
            (self.SECRET, "a@example.com", -1),
            (self.SECRET, "a@example.com", 1.5),
            (self.SECRET, "a@example.com", True),
        ):
            with self.subTest(args):
                with self.assertRaises(sb.IdentityError):
                    sb.compute_user_hash(*args)

    def test_identity_error_is_a_value_error(self):
        self.assertTrue(issubclass(sb.IdentityError, ValueError))


class WidgetSnippetTest(unittest.TestCase):
    def test_guest_snippet_has_no_visitor(self):
        html = sb.widget_snippet("bot-123", refresh_url="/simplyboost/identity")
        self.assertIn('window._CHATBOT_CONFIG_ = {"chat_bot_id":"bot-123"};', html)
        self.assertNotIn("visitor", html)
        self.assertIn('"https://get.simplyboost.io/widget.js?v="+Date.now()', html)

    def test_signed_in_snippet_carries_visitor_and_refresh_url(self):
        visitor = sb.visitor("s", "jane@example.com", name="Jane", ts=1792768282)
        config = sb.widget_config("bot-123", visitor, "/simplyboost/identity")
        self.assertEqual(config["visitor"]["refresh_url"], "/simplyboost/identity")
        self.assertEqual(config["visitor"]["user_hash"], visitor["user_hash"])
        self.assertNotIn("refresh_url", visitor)

    def test_values_cannot_break_out_of_the_script_element(self):
        visitor = {"email": "a@example.com", "name": "</script><script>alert(1)</script>&",
                   "ts": 1, "user_hash": "x"}
        html = sb.widget_snippet("bot", visitor, nonce='n"><x')
        self.assertNotIn("</script><script>alert", html)
        self.assertIn("\\u003c/script\\u003e", html)
        self.assertIn('nonce="n&quot;&gt;&lt;x"', html)
        self.assertEqual(html.count("</script>"), 2)

    def test_widget_url_with_query_string(self):
        html = sb.widget_snippet("bot", widget_url="https://cdn.example.com/w.js?x=1")
        # "&" is "&" once the browser parses the JavaScript string.
        self.assertIn('"https://cdn.example.com/w.js?x=1\\u0026v="+Date.now()', html)

    def test_bot_id_is_required(self):
        with self.assertRaises(sb.IdentityError):
            sb.widget_snippet(" ")


if __name__ == "__main__":
    unittest.main()
