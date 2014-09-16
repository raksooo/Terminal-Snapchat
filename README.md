To log in, add auth_token from other device to auth_token.txt
On android the token can be found in /data/data/com.snapchat.android/shared_prefs/com.snapchat.android_preferences.xml. Or hardcode you password into snapchat.php.

When running there's a few different commands:
logout: Logs you out and closes the application.
e|exit: Exits
s|sent: Lists sent snaps
r|received: Lists received snaps
a|all: Lists both sent and received snaps
f|friends: Lists friends
u|update: Reloads data
ls: Shows file in current directory
cd: Steps between directories
c|clr|clear: clears screen
v|view <index>: Shows snap with specified index, index is shown when running r|received
p|send <file> <to> <duration=10>: Sends a file to a username or index in friendslist. Default duration is 10s.
