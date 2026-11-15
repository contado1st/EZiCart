import 'package:intl/intl.dart';

/// Utilities for standard Philippine Time (UTC+8, Asia/Manila)
class PhilippineTime {
  static const Duration offset = Duration(hours: 8);

  /// Converts any DateTime to Philippine Time (UTC+8)
  static DateTime toPHT(DateTime dt) {
    return dt.toUtc().add(offset);
  }

  /// Current time in Philippine Time
  static DateTime now() {
    return DateTime.now().toUtc().add(offset);
  }

  /// Parse an ISO8601 or server date string and convert to Philippine Time
  static DateTime? parse(String? raw) {
    if (raw == null || raw.trim().isEmpty) return null;
    try {
      final parsed = DateTime.parse(raw.trim());
      return toPHT(parsed);
    } catch (_) {
      return null;
    }
  }

  /// Format date in Philippine Time
  static String format(String? raw, {String pattern = 'MMM d, yyyy h:mm a'}) {
    final dt = parse(raw);
    if (dt == null) return raw ?? '';
    return DateFormat(pattern).format(dt);
  }

  /// Format short chat timestamp for thread list (e.g. "3:45 PM", "Yesterday", "Nov 14")
  static String formatChatTime(String? raw) {
    final dt = parse(raw);
    if (dt == null) return '';
    final phtNow = now();
    final diff = phtNow.difference(dt);

    if (dt.year == phtNow.year && dt.month == phtNow.month && dt.day == phtNow.day) {
      return DateFormat('h:mm a').format(dt);
    } else if (diff.inDays <= 1 || (phtNow.day - dt.day == 1 && diff.inHours < 48)) {
      return 'Yesterday';
    } else if (diff.inDays < 7) {
      return DateFormat('EEE').format(dt);
    } else {
      return DateFormat('MMM d').format(dt);
    }
  }

  /// Format exact message timestamp inside chat bubble (e.g. "2:45 PM")
  static String formatBubbleTime(String? raw) {
    final dt = parse(raw);
    if (dt == null) return '';
    return DateFormat('h:mm a').format(dt);
  }

  /// Format date divider for conversations (e.g. "Today", "Yesterday", "Wednesday, Nov 15")
  static String formatDateDivider(String? raw) {
    final dt = parse(raw);
    if (dt == null) return '';
    final phtNow = now();
    if (dt.year == phtNow.year && dt.month == phtNow.month && dt.day == phtNow.day) {
      return 'Today';
    } else if (phtNow.difference(dt).inDays <= 1 && (phtNow.day - dt.day == 1 || phtNow.day - dt.day == -30)) {
      return 'Yesterday';
    } else if (dt.year == phtNow.year) {
      return DateFormat('EEEE, MMMM d').format(dt);
    } else {
      return DateFormat('MMMM d, yyyy').format(dt);
    }
  }

  /// Check if two date strings fall on the same day in Philippine Time
  static bool isSameDay(String? raw1, String? raw2) {
    final dt1 = parse(raw1);
    final dt2 = parse(raw2);
    if (dt1 == null || dt2 == null) return false;
    return dt1.year == dt2.year && dt1.month == dt2.month && dt1.day == dt2.day;
  }

  /// Format human-readable activity status (e.g. "Active now", "Active 5m ago")
  static String formatLastActive(String? raw, {bool isOnline = false}) {
    if (isOnline) return 'Active now';
    final dt = parse(raw);
    if (dt == null) return 'Offline';
    final phtNow = now();
    final diff = phtNow.difference(dt);

    if (diff.inMinutes < 5) {
      return 'Active now';
    } else if (diff.inMinutes < 60) {
      return 'Active ${diff.inMinutes}m ago';
    } else if (diff.inHours < 24) {
      return 'Active ${diff.inHours}h ago';
    } else if (diff.inDays == 1) {
      return 'Active yesterday';
    } else if (diff.inDays < 7) {
      return 'Active ${diff.inDays}d ago';
    } else {
      return 'Active ${DateFormat('MMM d').format(dt)}';
    }
  }
}
