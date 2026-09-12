#!/usr/bin/env bash
cmsa_mcp_curl() {
  command curl \
    -H 'Accept: application/json, text/event-stream' \
    "$@"
}
