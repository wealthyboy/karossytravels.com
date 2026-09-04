#!/usr/bin/env python3

import hashlib
import hmac
import json
import os
import subprocess
from http.server import BaseHTTPRequestHandler, ThreadingHTTPServer

HOST = "127.0.0.1"
PORT = int(os.environ.get("WEBHOOK_PORT", "9100"))
SECRET = os.environ["WEBHOOK_SECRET"].encode("utf-8")
EXPECTED_REPOSITORY = os.environ["WEBHOOK_REPOSITORY"]
EXPECTED_REF = os.environ.get("WEBHOOK_REF", "refs/heads/main")
DEPLOY_COMMAND = os.environ.get("WEBHOOK_DEPLOY_COMMAND", "/usr/local/bin/deploy-karossy")
DEPLOY_LOG = os.environ.get(
    "WEBHOOK_DEPLOY_LOG",
    "/home/forge/karossytravels.online/storage/logs/deploy.log",
)
MAX_BODY_BYTES = 2 * 1024 * 1024


class WebhookHandler(BaseHTTPRequestHandler):
    server_version = "KarossyWebhook/1.0"

    def reply(self, status: int, message: str) -> None:
        body = json.dumps({"message": message}).encode("utf-8")
        self.send_response(status)
        self.send_header("Content-Type", "application/json")
        self.send_header("Content-Length", str(len(body)))
        self.end_headers()
        self.wfile.write(body)

    def do_POST(self) -> None:
        if self.path != "/github":
            self.reply(404, "Not found")
            return

        try:
            content_length = int(self.headers.get("Content-Length", "0"))
        except ValueError:
            self.reply(400, "Invalid content length")
            return

        if content_length <= 0 or content_length > MAX_BODY_BYTES:
            self.reply(413, "Invalid payload size")
            return

        payload_body = self.rfile.read(content_length)
        received_signature = self.headers.get("X-Hub-Signature-256", "")
        expected_signature = "sha256=" + hmac.new(
            SECRET, payload_body, hashlib.sha256
        ).hexdigest()

        if not hmac.compare_digest(received_signature, expected_signature):
            self.reply(403, "Invalid signature")
            return

        event = self.headers.get("X-GitHub-Event", "")
        if event == "ping":
            self.reply(200, "Webhook ready")
            return
        if event != "push":
            self.reply(202, "Event ignored")
            return

        try:
            payload = json.loads(payload_body)
        except json.JSONDecodeError:
            self.reply(400, "Invalid JSON")
            return

        repository = payload.get("repository", {}).get("full_name")
        if repository != EXPECTED_REPOSITORY:
            self.reply(403, "Repository rejected")
            return
        if payload.get("ref") != EXPECTED_REF:
            self.reply(202, "Branch ignored")
            return
        if payload.get("deleted") is True:
            self.reply(202, "Deleted ref ignored")
            return

        os.makedirs(os.path.dirname(DEPLOY_LOG), exist_ok=True)
        log = open(DEPLOY_LOG, "ab", buffering=0)
        try:
            subprocess.Popen(
                [DEPLOY_COMMAND],
                stdin=subprocess.DEVNULL,
                stdout=log,
                stderr=subprocess.STDOUT,
                close_fds=True,
                start_new_session=True,
            )
        except OSError:
            log.close()
            self.reply(500, "Could not start deployment")
            return

        log.close()
        self.reply(202, "Deployment accepted")

    def log_message(self, format_string: str, *args: object) -> None:
        print("%s - %s" % (self.address_string(), format_string % args), flush=True)


if __name__ == "__main__":
    ThreadingHTTPServer((HOST, PORT), WebhookHandler).serve_forever()

