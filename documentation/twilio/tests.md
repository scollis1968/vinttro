A quick test for twilio

```
cd /var/www/vinttro-api && sudo node -e '
require("dotenv").config();
const twilio = require("twilio")(process.env.TWILIO_API_KEY_SID, process.env.TWILIO_API_KEY_SECRET, { accountSid: process.env.TWILIO_ACCOUNT_SID });
twilio.calls.create({
  to: "+447748633867",
  from: "+441473974005",
  twiml: "<Response><Say>Testing bare Twilio audio connection for twenty seconds.</Say><Pause length=\"20\"/></Response>"
}).then(c => console.log("Test Call SID:", c.sid)).catch(e => console.error("Err:", e.message));
'
```
Car 7
```
cd /var/www/vinttro-api && sudo node -e '
require("dotenv").config();
const twilio = require("twilio")(process.env.TWILIO_API_KEY_SID, process.env.TWILIO_API_KEY_SECRET, { accountSid: process.env.TWILIO_ACCOUNT_SID });
twilio.calls.create({
  to: "+447732603522",
  from: "+441473974005",
  twiml: "<Response><Say>Testing bare Twilio audio connection for twenty seconds.</Say><Pause length=\"20\"/></Response>"
}).then(c => console.log("Test Call SID:", c.sid)).catch(e => console.error("Err:", e.message));
'
```