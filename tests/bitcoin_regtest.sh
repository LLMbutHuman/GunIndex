#!/usr/bin/env bash
# Isolated Linux CI fixture. An optional argument supplies a cached release archive.
set -euo pipefail
umask 077

if [[ $(uname -s) != Linux || $(uname -m) != x86_64 ]]; then
  printf '%s\n' 'Bitcoin regtest requires Linux x86_64.' >&2
  exit 2
fi
if (( $# > 1 )); then
  printf '%s\n' 'Usage: bash tests/bitcoin_regtest.sh [bitcoin-30.4-x86_64-linux-gnu.tar.gz]' >&2
  exit 2
fi
if ! php -r 'exit(extension_loaded("curl") ? 0 : 1);'; then
  printf '%s\n' 'Bitcoin regtest requires PHP CLI with cURL.' >&2
  exit 2
fi

# https://bitcoincore.org/en/releases/30.4/
# https://raw.githubusercontent.com/bitcoin-core/guix.sigs/main/30.4/fanquake/all.SHA256SUMS
release=bitcoin-30.4
artifact="${release}-x86_64-linux-gnu.tar.gz"
checksum=051a18c595d9a27df23cb7187ff9fb343e90943cb142af751e12787806500c3d
probe="$(dirname -- "${BASH_SOURCE[0]}")/bitcoin_regtest_probe.php"
sandbox=$(mktemp -d /tmp/gunindex-bitcoin-regtest.XXXXXXXX)
node_pid=
phase=artifact

cleanup() {
  local result=$?
  if [[ -n $node_pid ]]; then
    kill "$node_pid" 2>/dev/null || true
    wait "$node_pid" 2>/dev/null || true
  fi
  if [[ $sandbox =~ ^/tmp/gunindex-bitcoin-regtest\.[[:alnum:]]{8}$ && -d $sandbox && ! -L $sandbox ]]; then
    rm -rf -- "$sandbox"
  fi
  if (( result != 0 )); then
    printf 'Bitcoin regtest failed during %s.\n' "$phase" >&2
  fi
}
trap cleanup EXIT
trap 'exit 130' INT
trap 'exit 143' TERM

archive="${1:-$sandbox/$artifact}"
if (( $# == 0 )); then
  curl --fail --silent --show-error --proto '=https' --connect-timeout 10 --max-time 180 \
    --retry 2 --output "$archive" "https://bitcoincore.org/bin/bitcoin-core-30.4/$artifact"
fi
printf '%s  %s\n' "$checksum" "$archive" | sha256sum --check --status
tar --extract --gzip --file "$archive" --directory "$sandbox" --no-same-owner --no-same-permissions
bin="$sandbox/$release/bin"

phase=daemon
datadir="$sandbox/node"
mkdir -- "$datadir"
touch -- "$datadir/bitcoin.conf"
port=$(php -r '
  $socket = stream_socket_server("tcp://127.0.0.1:0");
  if ($socket === false) exit(1);
  echo substr(strrchr(stream_socket_get_name($socket, false), ":"), 1);
')
cli=("$bin/bitcoin-cli" -regtest "-datadir=$datadir" -rpcconnect=127.0.0.1 "-rpcport=$port" -rpcclienttimeout=10)
cookie="$datadir/regtest/.cookie"
"$bin/bitcoind" -regtest "-datadir=$datadir" "-conf=$datadir/bitcoin.conf" \
  -listen=0 -discover=0 -dnsseed=0 -connect=0 -networkactive=0 \
  -rpcbind=127.0.0.1 -rpcallowip=127.0.0.1 "-rpcport=$port" \
  -printtoconsole=0 -daemon=0 -persistmempool=0 &
node_pid=$!

chain=
for (( attempt=0; attempt<120; attempt++ )); do
  kill -0 "$node_pid" 2>/dev/null
  if [[ -s $cookie ]] && chain=$("${cli[@]}" getblockchaininfo 2>/dev/null); then
    break
  fi
  sleep 0.25
done
php -r 'exit((json_decode(stream_get_contents(STDIN), true)["chain"] ?? null) === "regtest" ? 0 : 1);' <<<"$chain"
"${cli[@]}" getnetworkinfo | php -r '
  $network = json_decode(stream_get_contents(STDIN), true, 32, JSON_THROW_ON_ERROR);
  exit($network["networkactive"] === false && $network["connections"] === 0 ? 0 : 1);
'

phase=wallets
"${cli[@]}" createwallet gunindex-funding >/dev/null
"${cli[@]}" createwallet gunindex-descriptor-source >/dev/null
"${cli[@]}" -named createwallet wallet_name=gunindex-watch disable_private_keys=true blank=true >/dev/null
funding=( "${cli[@]}" -rpcwallet=gunindex-funding )
mining_address=$("${funding[@]}" getnewaddress '' bech32)
"${cli[@]}" generatetoaddress 101 "$mining_address" >/dev/null

descriptor=$("${cli[@]}" -rpcwallet=gunindex-descriptor-source listdescriptors false | php -r '
  $wallet = json_decode(stream_get_contents(STDIN), true, 64, JSON_THROW_ON_ERROR);
  foreach ($wallet["descriptors"] as $descriptor) {
    if ($descriptor["active"] && !$descriptor["internal"] && str_starts_with($descriptor["desc"], "wpkh(")) {
      echo json_encode([["desc" => $descriptor["desc"], "timestamp" => "now", "active" => true,
                        "internal" => false, "range" => [0, 32]]], JSON_THROW_ON_ERROR);
      exit(0);
    }
  }
  exit(1);
')
"${cli[@]}" unloadwallet gunindex-descriptor-source >/dev/null
"${cli[@]}" -rpcwallet=gunindex-watch importdescriptors "$descriptor" | php -r '
  exit((json_decode(stream_get_contents(STDIN), true)[0]["success"] ?? false) === true ? 0 : 1);
'

phase=address
address=$(php "$probe" allocate "$port" "$cookie")
phase=mempool
txid=$("${funding[@]}" -named sendtoaddress "address=$address" amount=0.00123456 fee_rate=2)
php "$probe" pending "$port" "$cookie" "$address" "$txid"
phase=confirmation
block=$("${cli[@]}" generatetoaddress 1 "$mining_address" | php -r '
  echo json_decode(stream_get_contents(STDIN), true, 16, JSON_THROW_ON_ERROR)[0];
')
php "$probe" confirmed "$port" "$cookie" "$address" "$txid" "$block"
phase=reorganization
"${cli[@]}" invalidateblock "$block"
php "$probe" reorg "$port" "$cookie" "$address" "$txid"
printf '%s\n' 'Bitcoin Core regtest passed: watch-only address, exact satoshis, canonical confirmation and reorganization.'
