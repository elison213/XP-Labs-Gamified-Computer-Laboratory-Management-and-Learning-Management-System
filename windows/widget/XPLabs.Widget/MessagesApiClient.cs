using System;
using System.Collections.Generic;
using System.IO;
using System.Text;
using System.Text.RegularExpressions;

namespace XPLabs.Widget
{
    internal sealed class InboxMessage
    {
        public int ThreadId { get; set; }
        public string SenderRole { get; set; }
        public string Body { get; set; }
        public string CreatedAt { get; set; }
        public string SenderName { get; set; }
        public bool IsPending { get; set; }
    }

    internal static class MessagesApiClient
    {
        public static List<InboxMessage> ParseInbox(string json)
        {
            var list = new List<InboxMessage>();
            if (string.IsNullOrWhiteSpace(json))
            {
                return list;
            }

            foreach (Match threadMatch in Regex.Matches(json, "\"id\"\\s*:\\s*(\\d+)[\\s\\S]*?\"messages\"\\s*:\\s*\\[([\\s\\S]*?)\\]\\s*\\}", RegexOptions.IgnoreCase))
            {
                if (!threadMatch.Success)
                {
                    continue;
                }
                var threadId = int.Parse(threadMatch.Groups[1].Value);
                var messagesBlock = threadMatch.Groups[2].Value;
                foreach (Match msgMatch in Regex.Matches(messagesBlock, "\\{[\\s\\S]*?\\}", RegexOptions.IgnoreCase))
                {
                    var block = msgMatch.Value;
                    list.Add(new InboxMessage
                    {
                        ThreadId = threadId,
                        SenderRole = JsonTiny.TryGetString(block, "sender_role", ""),
                        Body = JsonTiny.TryGetString(block, "body", ""),
                        CreatedAt = JsonTiny.TryGetString(block, "created_at", ""),
                        SenderName = (JsonTiny.TryGetString(block, "first_name", "") + " " + JsonTiny.TryGetString(block, "last_name", "")).Trim(),
                        IsPending = false
                    });
                }
            }

            return list;
        }

        public static string TryGetActiveLrn(string json)
        {
            var block = ExtractObject(json, "active_session");
            return JsonTiny.TryGetString(block, "lrn", "");
        }

        public static int TryGetLatestOpenThreadId(string json)
        {
            var bestId = 0;
            foreach (Match m in Regex.Matches(json, "\"id\"\\s*:\\s*(\\d+)", RegexOptions.IgnoreCase))
            {
                if (!m.Success)
                {
                    continue;
                }
                var id = int.Parse(m.Groups[1].Value);
                if (id > bestId)
                {
                    bestId = id;
                }
            }
            return bestId;
        }

        public static List<InboxMessage> LoadPendingFromFile(string path)
        {
            var list = new List<InboxMessage>();
            try
            {
                if (!File.Exists(path))
                {
                    return list;
                }
                var json = File.ReadAllText(path, Encoding.UTF8);
                var arrMatch = Regex.Match(json, "\"messages\"\\s*:\\s*\\[([\\s\\S]*?)\\]", RegexOptions.IgnoreCase);
                var scan = arrMatch.Success ? arrMatch.Groups[1].Value : json;
                foreach (Match m in Regex.Matches(scan, "\\{[\\s\\S]*?\\}", RegexOptions.IgnoreCase))
                {
                    var block = m.Value;
                    if (block.IndexOf("command_id", StringComparison.OrdinalIgnoreCase) < 0
                        && block.IndexOf("\"message\"", StringComparison.OrdinalIgnoreCase) < 0)
                    {
                        continue;
                    }
                    var cmdId = JsonTiny.TryGetInt(block, "command_id", 0);
                    var threadId = JsonTiny.TryGetInt(block, "thread_id", 0);
                    var body = JsonTiny.TryGetString(block, "message", "");
                    if (string.IsNullOrWhiteSpace(body))
                    {
                        body = JsonTiny.TryGetString(block, "body", "");
                    }
                    if (string.IsNullOrWhiteSpace(body))
                    {
                        continue;
                    }
                    list.Add(new InboxMessage
                    {
                        ThreadId = threadId,
                        SenderRole = "instructor",
                        Body = body,
                        CreatedAt = JsonTiny.TryGetString(block, "received_at", ""),
                        SenderName = "Instructor",
                        IsPending = true
                    });
                    if (cmdId > 0)
                    {
                        // keep for dedupe if needed later
                    }
                }
            }
            catch
            {
                // ignore
            }
            return list;
        }

        public static void ClearPendingFile(string path)
        {
            try
            {
                var dir = Path.GetDirectoryName(path);
                if (!string.IsNullOrWhiteSpace(dir) && !Directory.Exists(dir))
                {
                    Directory.CreateDirectory(dir);
                }
                File.WriteAllText(path, "{\"messages\":[]}", Encoding.UTF8);
            }
            catch
            {
                // ignore
            }
        }

        private static string ExtractObject(string json, string key)
        {
            var pattern = "\"" + Regex.Escape(key) + "\"\\s*:\\s*(\\{[\\s\\S]*?\\})(?=\\s*[,}])";
            var m = Regex.Match(json, pattern, RegexOptions.IgnoreCase);
            return m.Success ? m.Groups[1].Value : "";
        }
    }
}
