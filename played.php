<?php
if (iget("season")) {
  $season = iget("season");
} else {
  $season = CurrentSeason();
}
header("location:?view=games&season=" . $season . "&filter=tournaments&group=all");
