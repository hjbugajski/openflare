export function formatInterval(
  seconds: number,
  verbose = false,
): { value: number; unit: string; formatted: string } {
  if (seconds < 60) {
    const unit = verbose ? `second${seconds !== 1 ? 's' : ''}` : 's';
    return {
      value: seconds,
      unit,
      formatted: `${seconds}${verbose ? ' ' : ''}${unit}`,
    };
  }

  if (seconds < 3600) {
    const minutes = Math.floor(seconds / 60);
    const unit = verbose ? `minute${minutes !== 1 ? 's' : ''}` : 'm';
    return {
      value: minutes,
      unit,
      formatted: `${minutes}${verbose ? ' ' : ''}${unit}`,
    };
  }

  const hours = Math.floor(seconds / 3600);
  const unit = verbose ? `hour${hours !== 1 ? 's' : ''}` : 'h';
  return {
    value: hours,
    unit,
    formatted: `${hours}${verbose ? ' ' : ''}${unit}`,
  };
}
