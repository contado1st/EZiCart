import 'package:flutter/material.dart';

class AppTheme {
  static const Color primary = Color(0xFFBE2854);
  static const Color primaryHover = Color(0xFF9F2046);
  static const Color primarySoft = Color(0xFFFFF0F4);

  static const Color canvas = Color(0xFFF8F9FC);
  static const Color bg = canvas;
  static const Color surface = Color(0xFFFFFFFF);
  static const Color ink = Color(0xFF202634);
  static const Color muted = Color(0xFF657084);
  static const Color line = Color(0xFFE7E9EF);
  static const Color inputLine = Color(0xFFDCE0E8);

  /// Brand editorial & luxury heading font matching the web ("Bodoni Moda", Georgia, serif)
  static TextStyle displayFont({
    double? fontSize,
    FontWeight? fontWeight,
    Color? color,
    double? height,
    FontStyle? fontStyle,
    double? letterSpacing,
  }) {
    return TextStyle(
      fontFamily: 'Bodoni Moda',
      fontSize: fontSize,
      fontWeight: fontWeight,
      color: color,
      height: height,
      fontStyle: fontStyle,
      letterSpacing: letterSpacing,
    );
  }

  /// Primary application body & UI font matching the web ("Instrument Sans", sans-serif)
  static TextStyle bodyFont({
    double? fontSize,
    FontWeight? fontWeight,
    Color? color,
    double? height,
    FontStyle? fontStyle,
    double? letterSpacing,
  }) {
    return TextStyle(
      fontFamily: 'Instrument Sans',
      fontSize: fontSize,
      fontWeight: fontWeight,
      color: color,
      height: height,
      fontStyle: fontStyle,
      letterSpacing: letterSpacing,
    );
  }

  static ThemeData get lightTheme {
    return ThemeData(
      useMaterial3: true,
      fontFamily: 'Instrument Sans',
      scaffoldBackgroundColor: canvas,
      primaryColor: primary,
      colorScheme: const ColorScheme.light(
        primary: primary,
        surface: surface,
        onSurface: ink,
      ),
      textTheme: const TextTheme().apply(
        fontFamily: 'Instrument Sans',
        bodyColor: ink,
        displayColor: ink,
      ),
      primaryTextTheme: const TextTheme().apply(
        fontFamily: 'Instrument Sans',
        bodyColor: ink,
        displayColor: ink,
      ),
      appBarTheme: const AppBarTheme(
        backgroundColor: surface,
        elevation: 0,
        scrolledUnderElevation: 0,
        iconTheme: IconThemeData(color: ink),
        titleTextStyle: TextStyle(
          fontFamily: 'Instrument Sans',
          color: ink,
          fontSize: 18,
          fontWeight: FontWeight.w600,
        ),
      ),
      cardTheme: CardThemeData(
        color: surface,
        elevation: 0,
        shape: RoundedRectangleBorder(
          borderRadius: BorderRadius.circular(14),
          side: const BorderSide(color: line, width: 1),
        ),
      ),
      inputDecorationTheme: InputDecorationTheme(
        filled: true,
        fillColor: surface,
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 12,
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(7),
          borderSide: const BorderSide(color: inputLine, width: 1),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(7),
          borderSide: const BorderSide(color: primary, width: 2),
        ),
        hintStyle: const TextStyle(
          fontFamily: 'Instrument Sans',
          color: muted,
          fontSize: 13,
        ),
      ),
      elevatedButtonTheme: ElevatedButtonThemeData(
        style: ElevatedButton.styleFrom(
          backgroundColor: primary,
          foregroundColor: surface,
          elevation: 0,
          minimumSize: const Size(64, 44),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(7)),
          textStyle: const TextStyle(
            fontFamily: 'Instrument Sans',
            fontWeight: FontWeight.w600,
            fontSize: 14,
          ),
        ),
      ),
    );
  }
}
